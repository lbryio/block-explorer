package explorer

import (
	"encoding/json"
	"os"
	"reflect"
	"testing"
)

func TestRoutes(t *testing.T) {
	b, e := os.ReadFile("testdata/routes.json")
	if e != nil {
		t.Fatal(e)
	}
	var cases []struct {
		Path, Method, Action string
		Args                 []string
	}

	if e = json.Unmarshal(b, &cases); e != nil {
		t.Fatal(e)
	}
	if len(cases) < 50 {
		t.Fatal("missing route cases")
	}
	for _, c := range cases {
		m := matchRoute(c.Path, c.Method)
		if m.Action != c.Action {
			t.Errorf("%s %s: %s != expected %s", c.Method, c.Path, m.Action, c.Action)
		}
		if c.Action != "index" && !reflect.DeepEqual(m.Args, c.Args) {
			t.Errorf("%s arguments %v != expected %v", c.Path, m.Args, c.Args)
		}
	}
}
