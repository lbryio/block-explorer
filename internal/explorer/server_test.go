package explorer

import (
	"context"
	"encoding/json"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

type fakeStore struct {
	rows  []Row
	calls []string
	args  [][]any
}

func (f *fakeStore) Query(_ context.Context, q string, a ...any) ([]Row, error) {
	f.calls = append(f.calls, q)
	f.args = append(f.args, a)
	return f.rows, nil
}
func TestStatus(t *testing.T) {
	f := &fakeStore{rows: []Row{{"height": int64(25), "difficulty": 1234.567}}}
	s := Server{Store: f}
	w := httptest.NewRecorder()
	s.ServeHTTP(w, httptest.NewRequest("POST", "/api/v1/status", nil))
	want := `{"success":true,"status":{"height":25,"difficulty":"1234.57","price":"N\/A","hashrate":"N\/A"}}`
	if w.Body.String() != want {
		t.Fatal(w.Body.String())
	}
	if !strings.Contains(f.calls[0], "ORDER BY height DESC LIMIT 1") {
		t.Fatal(f.calls)
	}
}
func TestBalanceBoundParameters(t *testing.T) {
	f := &fakeStore{rows: []Row{}}
	s := Server{Store: f}
	w := httptest.NewRecorder()
	s.ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/address/abc/balance", nil))
	if w.Code != 400 || w.Body.String() != `{"error":true,"message":"Could not find address."}` {
		t.Fatal(w.Code, w.Body.String())
	}
	if f.args[0][0] != "abc" || strings.Contains(f.calls[0], "abc") {
		t.Fatal("address must be bound")
	}
}
func TestChartValidationAndEmptyShape(t *testing.T) {
	for _, tc := range []struct {
		method, path, want string
		code               int
	}{{"POST", "24h", `{"error":true,"message":"Invalid HTTP request method."}`, 400}, {"GET", "2h", `{"error":true,"message":"Invalid time period specified."}`, 400}, {"GET", "24h", `{"success":true,"data":[]}`, 200}} {
		f := &fakeStore{rows: []Row{}}
		s := Server{Store: f, Now: func() time.Time { return time.Date(2026, 11, 2, 13, 45, 0, 0, time.UTC) }}
		w := httptest.NewRecorder()
		s.ServeHTTP(w, httptest.NewRequest(tc.method, "/api/v1/charts/blocksize/"+tc.path, nil))
		if w.Code != tc.code || w.Body.String() != tc.want {
			t.Fatal(w.Code, w.Body.String())
		}
		if tc.code == 400 && len(f.calls) != 0 {
			t.Fatal("invalid request queried DB")
		}
		if tc.code == 200 && f.args[0][0] != "2026-11-01 13:00:00" {
			t.Fatal(f.args)
		}
	}
}
func TestPHPJSON(t *testing.T) {
	b, e := phpJSON(struct {
		Text string `json:"text"`
	}{"<é/😀>"})
	if e != nil || string(b) != `{"text":"<\u00e9\/\ud83d\ude00>"}` {
		t.Fatal(string(b), e)
	}
}
func TestCacheExpiry(t *testing.T) {
	now := time.Unix(1000, 0)
	c := Cache{Dir: t.TempDir(), Now: func() time.Time { return now }}
	if e := c.Put("inflation", []byte(`{"a":1}`), 10*time.Minute); e != nil {
		t.Fatal(e)
	}
	if b, ok := c.Get("inflation"); !ok || string(b) != `{"a":1}` {
		t.Fatal(string(b), ok)
	}
	now = now.Add(10 * time.Minute)
	if _, ok := c.Get("inflation"); ok {
		t.Fatal("expired hit")
	}
}

func TestAllTemplatesExecute(t *testing.T) {
	s := Server{Now: func() time.Time { return time.Unix(1700000100, 0) }}
	data := pageData{Tx: Row{"hash": "abc", "created_at": "2026-11-02 11:00:00", "value": 1, "input_count": 1, "output_count": 1}, Address: Row{"address": "abc"}, Claim: Row{"name": "claim", "claim_type": 1, "created_at": "2026-11-02 11:00:00"}, Title: "fixture", Highest: Row{"height": 50, "difficulty": 2}, RecentBlocks: []Row{{"height": 50, "difficulty": 2, "block_time": 1700000000, "block_size": 123, "tx_count": 1}}, RecentClaims: []Row{{"name": "safe <name>", "claim_id": "123", "claim_type": 1, "is_nsfw": 0, "thumbnail_url": "", "transaction_hash_id": "abc", "vout": 0}}, Block: Row{"height": 50, "difficulty": 2, "block_time": 1700000000, "block_size": 123}, Txs: []Row{}, Blocks: []Row{}, NumRecords: 50, PageLimit: 50, CurrentPage: 1, NumPages: 1, Begin: 1, End: 50}
	for _, name := range []string{"home", "realtime", "block", "blocks", "claims", "find", "claim", "address", "stats", "tx"} {
		t.Run(name, func(t *testing.T) {
			w := httptest.NewRecorder()
			s.render(w, httptest.NewRequest("GET", "http://explorer.lbry.com/", nil), name, data)
			if w.Code != 200 {
				t.Fatal(w.Body.String())
			}
			if !strings.Contains(w.Body.String(), "/css/main.css") {
				t.Fatal("missing layout")
			}
			if strings.Contains(w.Body.String(), "#ZgotmplZ") {
				t.Fatal("template URL rejected")
			}
			if name == "home" && !strings.Contains(w.Body.String(), "safe &lt;name&gt;") {
				t.Fatal("claim text not escaped")
			}
		})
	}
}

func TestHomepageFallbackAndPageRedirects(t *testing.T) {
	for _, path := range []string{"/unknown", "/api/v1/address/abc/transactions", "/tag/abc", "/api/v1/address/abc/tag", "/api/v1/status/extra", "/css/missing.css", "/css/", "/embed.go"} {
		f := &fakeStore{rows: []Row{}}
		s := Server{Store: f}
		w := httptest.NewRecorder()
		s.ServeHTTP(w, httptest.NewRequest("GET", path, nil))
		if w.Code != 200 || !strings.Contains(w.Body.String(), "LBRY Block Explorer &bull; Home") {
			t.Fatalf("%s: %d %s", path, w.Code, w.Body.String())
		}
	}
	for _, path := range []string{"/tx", "/tx/missing", "/blocks/-1", "/claims/missing", "/address/short"} {
		f := &fakeStore{rows: []Row{}}
		s := Server{Store: f}
		w := httptest.NewRecorder()
		s.ServeHTTP(w, httptest.NewRequest("GET", path, nil))
		if w.Code != 302 || w.Header().Get("Location") != "http://example.com/" {
			t.Fatalf("%s: %d %s", path, w.Code, w.Body.String())
		}
	}
}
func TestDescriptionEscapesAndLinks(t *testing.T) {
	got := string(description("See https://example.com/page\n<script>alert(1)</script>"))
	if !strings.Contains(got, `<a href="https://example.com/page" target="_blank" rel="nofollow">https://example.com/page</a><br />`) {
		t.Fatal(got)
	}
	if strings.Contains(got, "<script>") || !strings.Contains(got, "&lt;script&gt;") {
		t.Fatal(got)
	}
	if string(safeURL("javascript:alert(1)")) != "#" {
		t.Fatal("unsafe scheme")
	}
}
func TestClaimEntityJSONTypes(t *testing.T) {
	rows := claimEntities([]Row{{"is_nsfw": int64(0), "is_cert_valid": int64(1), "has_claim_list": nil, "created_at": "2026-11-02 12:00:00", "value_as_json": `{"text":"é"}`, "claim_type": int64(2)}})
	r := rows[0]
	if r["is_nsfw"] != false || r["is_cert_valid"] != true || r["has_claim_list"] != nil || r["created_at"] != "2026-11-02T12:00:00+00:00" || r["claim_type"] != int64(2) {
		t.Fatal(r)
	}
	if _, ok := r["value_as_json"].(jsonObject); !ok {
		t.Fatal(r)
	}
}
func TestCachedJSONDoesNotDoubleEscape(t *testing.T) {
	b, e := phpJSON(struct {
		Data json.RawMessage `json:"data"`
	}{json.RawMessage(`{"url":"https:\/\/example.com"}`)})
	if e != nil || string(b) != `{"data":{"url":"https:\/\/example.com"}}` {
		t.Fatal(string(b), e)
	}
}
