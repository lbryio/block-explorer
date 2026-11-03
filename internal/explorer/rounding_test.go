package explorer

import (
	"context"
	"encoding/json"
	"net/http/httptest"
	"os"
	"regexp"
	"strconv"
	"testing"
	"time"
)

func TestRoundingBoundaries(t *testing.T) {
	b, e := os.ReadFile("testdata/formatting.json")
	if e != nil {
		t.Fatal(e)
	}
	var f struct {
		Rounds []struct {
			Value    float64
			Places   int
			Expected string
		}
	}
	if e = json.Unmarshal(b, &f); e != nil {
		t.Fatal(e)
	}
	if len(f.Rounds) < 30 {
		t.Fatal("missing rounding cases")
	}
	for _, c := range f.Rounds {
		if got := fixed(c.Value, c.Places); got != c.Expected {
			t.Errorf("%v precision %d: %s != expected %s", c.Value, c.Places, got, c.Expected)
		}
	}
	s := Server{Store: &fakeStore{rows: []Row{{"height": 1, "difficulty": 1.005}}}}
	w := httptest.NewRecorder()
	s.ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/status", nil))
	if w.Body.String() != `{"success":true,"status":{"height":1,"difficulty":"1.01","price":"N\/A","hashrate":"N\/A"}}` {
		t.Fatal(w.Body.String())
	}
}

type delayedStore struct{}

func (delayedStore) Query(context.Context, string, ...any) ([]Row, error) {
	time.Sleep(3 * time.Millisecond)
	return []Row{}, nil
}
func TestFooterMeasuresRequestWork(t *testing.T) {
	w := httptest.NewRecorder()
	s := Server{Store: delayedStore{}}
	s.ServeHTTP(w, httptest.NewRequest("GET", "/", nil))
	match := regexp.MustCompile(`Page took ([0-9]+)ms`).FindStringSubmatch(w.Body.String())
	if len(match) != 2 {
		t.Fatal("missing timing footer")
	}
	elapsed, _ := strconv.Atoi(match[1])
	if elapsed < 5 {
		t.Fatal("footer omitted database time", elapsed)
	}
}
