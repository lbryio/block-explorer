package explorer

import (
	"encoding/base64"
	"encoding/json"
	"net/http/httptest"
	"os"
	"testing"
)

func TestQueryAndBlockingBoundaries(t *testing.T) {
	b, e := os.ReadFile("testdata/boundaries.json")
	if e != nil {
		t.Fatal(e)
	}
	var f struct {
		Queries []struct {
			Raw, Q string
			Page   int64
		}
		Blocked []struct {
			Claim      string
			Publisher  *string
			Body       string
			BodyBase64 string `json:"body_base64"`
			Expected   bool
		}
	}
	if e = json.Unmarshal(b, &f); e != nil {
		t.Fatal(e)
	}
	for _, c := range f.Queries {
		r := httptest.NewRequest("GET", "/?"+c.Raw, nil)
		if q := queryString(r, "q"); q != c.Q {
			t.Errorf("%s: q %q != expected %q", c.Raw, q, c.Q)
		}
		if page := queryInt(r, "page"); int64(page) != c.Page {
			t.Errorf("%s: page %d != expected %d", c.Raw, page, c.Page)
		}
	}
	for _, c := range f.Blocked {
		row := Row{"claim_id": c.Claim}
		if c.Publisher != nil {
			row["publisher_id"] = *c.Publisher
		}
		body := []byte(c.Body)
		if c.BodyBase64 != "" {
			body, e = base64.StdEncoding.DecodeString(c.BodyBase64)
			if e != nil {
				t.Fatal(e)
			}
		}
		markBlocked([]Row{row}, parseBlocked(body))
		if got := truth(row["isBlocked"]); got != c.Expected {
			t.Errorf("%s claim %q publisher %v: %v != expected %v", c.Body, c.Claim, c.Publisher, got, c.Expected)
		}
	}
}
