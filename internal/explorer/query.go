package explorer

import (
	"net/http"
	"strings"
)

func phpTrim(s string) string { return strings.Trim(s, " \t\n\r\x00\x0b") }

type queryParameter struct {
	Value   string
	Array   bool
	Present bool
}

func queryParam(r *http.Request, key string) queryParameter {
	var result queryParameter
	for _, part := range strings.Split(r.URL.RawQuery, "&") {
		pair := strings.SplitN(part, "=", 2)
		name := phpQueryDecode(pair[0])
		value := ""
		if len(pair) > 1 {
			value = phpQueryDecode(pair[1])
		}
		if name == key {
			result = queryParameter{value, false, true}
			continue
		}
		if strings.HasPrefix(name, key+"[") && strings.Contains(name[len(key)+1:], "]") {
			result = queryParameter{"", true, true}
		}
	}
	return result
}
func queryString(r *http.Request, key string) string {
	v := queryParam(r, key)
	if v.Array {
		return "Array"
	}
	return v.Value
}
func queryInt(r *http.Request, key string) int {
	v := queryParam(r, key)
	if v.Array {
		return 1
	}
	return phpInt(v.Value)
}
func queryScalar(r *http.Request, key string) string {
	v := queryParam(r, key)
	if v.Array {
		return ""
	}
	return v.Value
}

func phpQueryDecode(s string) string {
	var out strings.Builder
	hex := func(b byte) int {
		switch {
		case b >= '0' && b <= '9':
			return int(b - '0')
		case b >= 'a' && b <= 'f':
			return int(b-'a') + 10
		case b >= 'A' && b <= 'F':
			return int(b-'A') + 10
		}
		return -1
	}
	for i := 0; i < len(s); i++ {
		if s[i] == '+' {
			out.WriteByte(' ')
		} else if s[i] == '%' && i+2 < len(s) && hex(s[i+1]) >= 0 && hex(s[i+2]) >= 0 {
			out.WriteByte(byte(hex(s[i+1])*16 + hex(s[i+2])))
			i += 2
		} else {
			out.WriteByte(s[i])
		}
	}
	return out.String()
}
