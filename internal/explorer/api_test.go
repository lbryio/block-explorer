package explorer

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

type scriptedStore struct {
	sets    [][]Row
	queries []string
	args    [][]any
}

func (f *scriptedStore) Query(_ context.Context, q string, a ...any) ([]Row, error) {
	f.queries = append(f.queries, q)
	f.args = append(f.args, a)
	if len(f.sets) == 0 {
		return []Row{}, nil
	}
	rows := f.sets[0]
	f.sets = f.sets[1:]
	return rows, nil
}
func TestInflationSamplingAndCache(t *testing.T) {
	f := &scriptedStore{sets: [][]Row{{{"height": int64(2501)}}, {{"height": 0, "block_time": 1}, {"height": 2000, "block_time": 2}, {"height": 2501, "block_time": 3}}, {{"height": 2501, "block_time": 3}, {"height": 2500, "block_time": 2}}}}
	s := Server{Store: f, Cache: Cache{Dir: t.TempDir()}}
	var bodies []string
	for i := 0; i < 2; i++ {
		w := httptest.NewRecorder()
		s.ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/charts/inflation", nil))
		if w.Code != 200 {
			t.Fatal(w.Code, w.Body.String())
		}
		bodies = append(bodies, w.Body.String())
	}
	if bodies[0] != bodies[1] {
		t.Fatal("cache changed bytes")
	}
	if len(f.queries) != 3 {
		t.Fatal(f.queries)
	}
	if len(f.args[1]) != 4 || f.args[1][3] != 2501 {
		t.Fatal(f.args)
	}
	var result struct {
		Data struct {
			Recent []Row
			Last   int `json:"last_reward_block"`
		}
	}
	if e := json.Unmarshal([]byte(bodies[0]), &result); e != nil {
		t.Fatal(e)
	}
	if number(result.Data.Recent[0]["height"]) != 2500 || result.Data.Last != 4071017 {
		t.Fatal(bodies[0])
	}
}
func TestClaimsCursorPrecedence(t *testing.T) {
	for _, query := range []string{"after=4&before=9&sort=oldest", "before=-2&sort=popular&nsfw=True", "before=7&sort=oldest&nsfw=true"} {
		f := &scriptedStore{}
		s := Server{Store: f}
		w := httptest.NewRecorder()
		s.ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/claims/browse?"+query, nil))
		if w.Body.String() != `{"success":true,"claims":[],"total":23000000}` {
			t.Fatal(w.Body.String())
		}
		q := f.queries[0]
		switch {
		case strings.Contains(query, "after="):
			if !strings.Contains(q, "Claims.id > ?") || !strings.Contains(q, "ORDER BY Claims.id ASC") {
				t.Fatal(q)
			}
		case strings.Contains(query, "before=-"):
			if strings.Contains(q, "Claims.id <") {
				t.Fatal(q)
			}
		default:
			if !strings.Contains(q, "Claims.id < ?") || !strings.Contains(q, "ORDER BY Claims.id DESC") {
				t.Fatal(q)
			}
		}
		if strings.HasSuffix(query, "nsfw=true") && strings.Contains(q, "is_nsfw !=") {
			t.Fatal(q)
		}
	}
}
func TestUTXOAtomicAndShape(t *testing.T) {
	f := &scriptedStore{sets: [][]Row{{{"id": int64(2), "address": "addr"}}, {{"transaction_hash": "tx", "vout": int64(1), "value": 1.23456789, "address_list": "[\"addr\"]", "script_pub_key_asm": "op /", "script_pub_key_hex": "01", "type": "pubkeyhash", "required_signatures": int64(1), "confirmations": int64(7)}}}}
	s := Server{Store: f}
	w := httptest.NewRecorder()
	s.ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/address/addr,unknown/utxo", nil))
	want := `{"success":true,"utxo":[{"transaction_hash":"tx","output_index":1,"value":123456789,"addresses":["addr"],"script":"op \/","script_hex":"01","script_type":"pubkeyhash","required_signatures":1,"spent":false,"confirmations":7}]}`
	if w.Body.String() != want {
		t.Fatal(w.Body.String())
	}
	if !strings.Contains(f.queries[1], "JSON_CONTAINS(O.address_list, JSON_QUOTE(?))") || len(f.args[1]) != 2 {
		t.Fatal(f.queries, f.args)
	}
}
func TestSupplyDecimalAndCache(t *testing.T) {
	f := &scriptedStore{sets: [][]Row{{{"balance": 10.12345678}}, {{"balance": 1.00000001}}}}
	s := Server{Store: f, Cache: Cache{Dir: t.TempDir()}}
	for i := 0; i < 2; i++ {
		w := httptest.NewRecorder()
		s.ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/supply", nil))
		if w.Body.String() != `{"success":true,"utxosupply":{"total":"10.12345678","circulating":"9.12345677"}}` {
			t.Fatal(w.Body.String())
		}
	}
	if len(f.queries) != 2 {
		t.Fatal(f.queries)
	}
	if len(f.args[1]) != 13 {
		t.Fatal(f.args)
	}
}

type roundTrip func(*http.Request) (*http.Response, error)

func (f roundTrip) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }
func TestBlockedListRequestCachingAndPublisher(t *testing.T) {
	calls := 0
	client := &http.Client{Transport: roundTrip(func(r *http.Request) (*http.Response, error) {
		calls++
		if r.URL.String() != blockedListURL || r.Method != "GET" {
			t.Fatal(r.Method, r.URL.String())
		}
		return &http.Response{StatusCode: 503, Body: http.NoBody, Header: http.Header{}}, nil
	})}
	now := time.Unix(1, 0)
	s := Server{Cache: Cache{Dir: t.TempDir(), Now: func() time.Time { return now }}, HTTPClient: client}
	s.blocked(context.Background())
	s.blocked(context.Background())
	if calls != 1 {
		t.Fatal("non-2xx response not cached", calls)
	}
	now = now.Add(10 * time.Minute)
	client.Transport = roundTrip(func(r *http.Request) (*http.Response, error) {
		calls++
		return &http.Response{StatusCode: 200, Body: io.NopCloser(strings.NewReader(`{"data":[{"claim_id":"publisher"}]}`)), Header: http.Header{}}, nil
	})
	list := s.blocked(context.Background())
	rows := []Row{{"claim_id": "claim", "publisher_id": "publisher", "thumbnail_url": "https://image"}}
	markBlocked(rows, list)
	if !truth(rows[0]["isBlocked"]) || rows[0]["thumbnail_url"] != nil {
		t.Fatal(rows)
	}
}

func TestPrimitiveAPICacheEntriesAreMisses(t *testing.T) {
	for _, key := range []string{"inflation_chart", "utxo_supply"} {
		for _, body := range []string{`null`, `false`, `"wrong type"`} {
			c := Cache{Dir: t.TempDir()}
			if e := c.Put(key, []byte(body), time.Hour); e != nil {
				t.Fatal(e)
			}
			s := Server{Cache: c}
			if _, hit := s.cachedArray(key); hit {
				t.Fatalf("primitive cache body accepted: %s %s", key, body)
			}
		}
	}
	c := Cache{Dir: t.TempDir()}
	c.Put("inflation_chart", []byte(`[]`), time.Hour)
	s := Server{Cache: c}
	if _, hit := s.cachedArray("inflation_chart"); !hit {
		t.Fatal("empty array is a valid legacy cache hit")
	}
}
func TestNullBlockedCacheEntryIsMiss(t *testing.T) {
	calls := 0
	s := Server{Cache: Cache{Dir: t.TempDir()}, HTTPClient: &http.Client{Transport: roundTrip(func(r *http.Request) (*http.Response, error) {
		calls++
		return &http.Response{StatusCode: 200, Header: http.Header{}, Body: io.NopCloser(strings.NewReader(`{"data":[]}`))}, nil
	})}}
	s.Cache.Put("list_blocked", []byte(`null`), time.Hour)
	s.blocked(context.Background())
	if calls != 1 {
		t.Fatal("null blocked-list cache must fetch")
	}
}
