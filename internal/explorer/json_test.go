package explorer

import (
	"encoding/json"
	"math"
	"os"
	"testing"
)

func TestJSONNumbers(t *testing.T) {
	b, e := os.ReadFile("testdata/formatting.json")
	if e != nil {
		t.Fatal(e)
	}
	var f struct {
		JSON []struct {
			Value   json.Number
			Encoded string
		}
	}
	if e = json.Unmarshal(b, &f); e != nil {
		t.Fatal(e)
	}
	if len(f.JSON) == 0 {
		t.Fatal("no numeric fixtures")
	}
	for _, c := range f.JSON {
		var value any = c.Value
		if string(c.Value) == "-0" {
			value = math.Copysign(0, -1)
		}
		b, e := phpJSON(value)
		if e != nil || string(b) != c.Encoded {
			t.Errorf("%s => %s != expected %s (%v)", c.Value, b, c.Encoded, e)
		}
	}
	for _, v := range []any{string([]byte{0xff}), Row{"bad": string([]byte{0xc3})}} {
		if _, e = phpJSON(v); e == nil {
			t.Fatal("invalid UTF-8 accepted")
		}
	}
}
func TestCakeJSONAssociativeArrays(t *testing.T) {
	for _, c := range []struct{ in, want string }{{`{}`, `[]`}, {`{"0":"a","1":"b"}`, `["a","b"]`}, {`{"long":{},"z":1.0}`, `{"long":[],"z":1}`}} {
		v, e := decodeOrderedJSON(c.in)
		if e != nil {
			t.Fatal(e)
		}
		b, e := phpJSON(cakeJSONArray(v))
		if e != nil || string(b) != c.want {
			t.Fatal(string(b), c.want, e)
		}
	}
}
