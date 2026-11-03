package explorer

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"math"
	"net/http"
	"reflect"
	"sort"
	"strconv"
	"strings"
	"unicode/utf16"
	"unicode/utf8"
)

// PHP defaults preserve insertion order, escape Unicode and slashes, reject
// malformed UTF-8, and switch float notation outside exponents [-4,16].
func phpJSON(v any) ([]byte, error) {
	var out bytes.Buffer
	if err := appendPHPJSON(&out, reflect.ValueOf(v)); err != nil {
		return nil, err
	}
	return out.Bytes(), nil
}
func appendJSONString(out *bytes.Buffer, s string) error {
	if !utf8.ValidString(s) {
		return errors.New("malformed UTF-8 characters")
	}
	out.WriteByte('"')
	for _, r := range s {
		switch r {
		case '"':
			out.WriteString(`\"`)
		case '\\':
			out.WriteString(`\\`)
		case '/':
			out.WriteString(`\/`)
		case '\b':
			out.WriteString(`\b`)
		case '\f':
			out.WriteString(`\f`)
		case '\n':
			out.WriteString(`\n`)
		case '\r':
			out.WriteString(`\r`)
		case '\t':
			out.WriteString(`\t`)
		default:
			if r < 32 || r > 127 {
				if r <= 0xffff {
					fmt.Fprintf(out, `\u%04x`, r)
				} else {
					a, b := utf16.EncodeRune(r)
					fmt.Fprintf(out, `\u%04x\u%04x`, a, b)
				}
			} else {
				out.WriteRune(r)
			}
		}
	}
	out.WriteByte('"')
	return nil
}
func phpFloat(f float64) (string, error) {
	if math.IsNaN(f) || math.IsInf(f, 0) {
		return "", errors.New("Inf and NaN cannot be JSON encoded")
	}
	e := strconv.FormatFloat(f, 'e', -1, 64)
	i := strings.IndexByte(e, 'e')
	exponent, _ := strconv.Atoi(e[i+1:])
	if exponent < -4 || exponent >= 17 {
		mantissa := e[:i]
		if !strings.Contains(mantissa, ".") {
			mantissa += ".0"
		}
		return mantissa + fmt.Sprintf("e%+d", exponent), nil
	}
	return strconv.FormatFloat(f, 'f', -1, 64), nil
}

type jsonField struct {
	Key   string
	Value any
}
type jsonObject []jsonField

func appendObject(out *bytes.Buffer, fields []jsonField) error {
	out.WriteByte('{')
	for i, f := range fields {
		if i > 0 {
			out.WriteByte(',')
		}
		if e := appendJSONString(out, f.Key); e != nil {
			return e
		}
		out.WriteByte(':')
		if e := appendPHPJSON(out, reflect.ValueOf(f.Value)); e != nil {
			return e
		}
	}
	out.WriteByte('}')
	return nil
}
func rowFields(r Row) []jsonField {
	keys, ok := r["__columns"].([]string)
	if !ok {
		for key := range r {
			if key != "__columns" {
				keys = append(keys, key)
			}
		}
		sort.Strings(keys)
	}
	fields := make([]jsonField, 0, len(keys))
	for _, key := range keys {
		fields = append(fields, jsonField{key, r[key]})
	}
	return fields
}
func appendPHPJSON(out *bytes.Buffer, v reflect.Value) error {
	if !v.IsValid() {
		out.WriteString("null")
		return nil
	}
	if v.Kind() == reflect.Interface || v.Kind() == reflect.Pointer {
		if v.IsNil() {
			out.WriteString("null")
			return nil
		}
		return appendPHPJSON(out, v.Elem())
	}
	if v.CanInterface() {
		switch value := v.Interface().(type) {
		case Row:
			return appendObject(out, rowFields(value))
		case jsonObject:
			return appendObject(out, value)
		case json.RawMessage:
			if !json.Valid(value) {
				return errors.New("invalid raw JSON")
			}
			out.Write(value)
			return nil
		case json.Number:
			if i, e := value.Int64(); e == nil {
				out.WriteString(strconv.FormatInt(i, 10))
				return nil
			}
			f, e := value.Float64()
			if e != nil {
				return e
			}
			s, e := phpFloat(f)
			if e != nil {
				return e
			}
			out.WriteString(s)
			return nil
		}
	}
	switch v.Kind() {
	case reflect.Bool:
		out.WriteString(strconv.FormatBool(v.Bool()))
	case reflect.String:
		return appendJSONString(out, v.String())
	case reflect.Int, reflect.Int8, reflect.Int16, reflect.Int32, reflect.Int64:
		out.WriteString(strconv.FormatInt(v.Int(), 10))
	case reflect.Uint, reflect.Uint8, reflect.Uint16, reflect.Uint32, reflect.Uint64:
		out.WriteString(strconv.FormatUint(v.Uint(), 10))
	case reflect.Float32, reflect.Float64:
		s, e := phpFloat(v.Float())
		if e != nil {
			return e
		}
		out.WriteString(s)
	case reflect.Slice, reflect.Array:
		if v.Kind() == reflect.Slice && v.IsNil() {
			out.WriteString("null")
			return nil
		}
		out.WriteByte('[')
		for i := 0; i < v.Len(); i++ {
			if i > 0 {
				out.WriteByte(',')
			}
			if e := appendPHPJSON(out, v.Index(i)); e != nil {
				return e
			}
		}
		out.WriteByte(']')
	case reflect.Map:
		if v.IsNil() {
			out.WriteString("null")
			return nil
		}
		if v.Type().Key().Kind() != reflect.String {
			return errors.New("nonstring JSON map key")
		}
		keys := v.MapKeys()
		sort.Slice(keys, func(i, j int) bool { return keys[i].String() < keys[j].String() })
		fields := make([]jsonField, 0, len(keys))
		for _, key := range keys {
			fields = append(fields, jsonField{key.String(), v.MapIndex(key).Interface()})
		}
		return appendObject(out, fields)
	case reflect.Struct:
		fields := []jsonField{}
		typ := v.Type()
		for i := 0; i < v.NumField(); i++ {
			field := typ.Field(i)
			if !field.IsExported() {
				continue
			}
			tag := strings.Split(field.Tag.Get("json"), ",")
			if tag[0] == "-" {
				continue
			}
			if len(tag) > 1 && tag[1] == "omitempty" && v.Field(i).IsZero() {
				continue
			}
			key := tag[0]
			if key == "" {
				key = field.Name
			}
			fields = append(fields, jsonField{key, v.Field(i).Interface()})
		}
		return appendObject(out, fields)
	default:
		return fmt.Errorf("unsupported JSON type %s", v.Kind())
	}
	return nil
}
func sendJSON(w http.ResponseWriter, status int, v any) {
	b, e := phpJSON(v)
	if e != nil {
		internalError(w)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	w.Write(b)
}
func jsonError(w http.ResponseWriter, status int, message string) {
	sendJSON(w, status, struct {
		Error   bool   `json:"error"`
		Message string `json:"message"`
	}{true, message})
}
func (r Row) MarshalJSON() ([]byte, error) { return phpJSON(r) }
func orderedRows(rows []Row, keys ...string) []Row {
	if rows == nil {
		return []Row{}
	}
	for _, r := range rows {
		r["__columns"] = keys
	}
	return rows
}

// Decode DB JSON in source order, retaining an empty object as {} rather than [].
func decodeOrderedJSON(s string) (any, error) {
	d := json.NewDecoder(strings.NewReader(s))
	d.UseNumber()
	return readJSONValue(d)
}
func readJSONValue(d *json.Decoder) (any, error) {
	token, e := d.Token()
	if e != nil {
		return nil, e
	}
	if delimiter, ok := token.(json.Delim); ok {
		switch delimiter {
		case '{':
			obj := jsonObject{}
			for d.More() {
				key, e := d.Token()
				if e != nil {
					return nil, e
				}
				value, e := readJSONValue(d)
				if e != nil {
					return nil, e
				}
				obj = append(obj, jsonField{key.(string), value})
			}
			_, e = d.Token()
			return obj, e
		case '[':
			arr := []any{}
			for d.More() {
				value, e := readJSONValue(d)
				if e != nil {
					return nil, e
				}
				arr = append(arr, value)
			}
			_, e = d.Token()
			return arr, e
		}
	}
	return token, nil
}

// Cake JsonType decodes JSON objects to PHP associative arrays. Empty objects
// and objects whose numeric keys form 0..n-1 consequently serialize as arrays.
func cakeJSONArray(v any) any {
	switch value := v.(type) {
	case jsonObject:
		if len(value) == 0 {
			return []any{}
		}
		sequential := true
		for i, f := range value {
			value[i].Value = cakeJSONArray(f.Value)
			if f.Key != strconv.Itoa(i) {
				sequential = false
			}
		}
		if sequential {
			arr := make([]any, len(value))
			for i, f := range value {
				arr[i] = f.Value
			}
			return arr
		}
		return value
	case []any:
		for i, item := range value {
			value[i] = cakeJSONArray(item)
		}
		return value
	}
	return v
}
