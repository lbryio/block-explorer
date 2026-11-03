package explorer

import (
	"bytes"
	"context"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"net/http/httptrace"
	"net/url"
	"strings"
	"sync/atomic"
	"testing"
	"time"
)

func TestBlockedTransportFailureIsUncached(t *testing.T) {
	calls := 0
	s := Server{Cache: Cache{Dir: t.TempDir()}, HTTPClient: &http.Client{Transport: roundTrip(func(r *http.Request) (*http.Response, error) { calls++; return nil, errors.New("unavailable") })}}
	for i := 0; i < 2; i++ {
		if len(s.blocked(context.Background())) != 0 {
			t.Fatal("failure should produce empty list")
		}
	}
	if calls != 2 {
		t.Fatal("transport failure was cached")
	}
	if _, ok := s.Cache.Get("list_blocked"); ok {
		t.Fatal("failed request populated cache")
	}
}
func TestBlockedMalformedBodyIsCached(t *testing.T) {
	calls := 0
	s := Server{Cache: Cache{Dir: t.TempDir()}, HTTPClient: &http.Client{Transport: roundTrip(func(r *http.Request) (*http.Response, error) {
		calls++
		return &http.Response{StatusCode: 200, Body: io.NopCloser(strings.NewReader("not JSON")), Header: http.Header{}}, nil
	})}}
	s.blocked(context.Background())
	s.blocked(context.Background())
	if calls != 1 {
		t.Fatal("successful malformed body must be cached")
	}
}
func TestBlockedContextDeadline(t *testing.T) {
	s := Server{Cache: Cache{Dir: t.TempDir()}, HTTPClient: &http.Client{Transport: roundTrip(func(r *http.Request) (*http.Response, error) { <-r.Context().Done(); return nil, r.Context().Err() })}}
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Millisecond)
	defer cancel()
	if len(s.blocked(ctx)) != 0 {
		t.Fatal("deadline fallback")
	}
	if _, ok := s.Cache.Get("list_blocked"); ok {
		t.Fatal("deadline was cached")
	}
	client := blockedHTTPClient()
	if client.Timeout != 15*time.Second {
		t.Fatal(client.Timeout)
	}
	transport := client.Transport.(*http.Transport)
	if !transport.DisableCompression || transport.TLSHandshakeTimeout != 5*time.Second {
		t.Fatal("transport compatibility")
	}
}
func TestBlockedRedirectAndHeaders(t *testing.T) {
	var paths []string
	fixture := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != "GET" || r.UserAgent() != "" || r.Header.Get("Accept-Encoding") != "" || r.Header.Get("Accept") != "*/*" || r.Header.Get("Referer") != "" {
			t.Error("unexpected request headers/method", r.Method, r.Header)
		}
		paths = append(paths, r.URL.RequestURI())
		if r.URL.Path == "/file/list_blocked" {
			w.Header().Set("Location", "/final")
			w.WriteHeader(302)
			return
		}
		io.WriteString(w, `{"data":[{"claim_id":"blocked"}]}`)
	}))
	defer fixture.Close()
	target, _ := url.Parse(fixture.URL)
	client := blockedHTTPClient()
	transport := client.Transport
	client.Transport = roundTrip(func(r *http.Request) (*http.Response, error) {
		copy := r.Clone(r.Context())
		u := *r.URL
		u.Host = target.Host
		u.Scheme = target.Scheme
		copy.URL = &u
		return transport.RoundTrip(copy)
	})
	s := Server{Cache: Cache{Dir: t.TempDir()}, HTTPClient: client}
	if !s.blocked(context.Background())["blocked"] {
		t.Fatal("redirected body missing")
	}
	if len(paths) != 2 || paths[0] != "/file/list_blocked?with_claim_id=true" || paths[1] != "/final" {
		t.Fatal(paths)
	}
}
func TestBlockedRedirectLimit(t *testing.T) {
	var calls atomic.Int64
	fixture := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls.Add(1)
		w.Header().Set("Location", "/loop")
		w.WriteHeader(http.StatusFound)
	}))
	defer fixture.Close()
	resp, err := blockedHTTPClient().Get(fixture.URL + "/loop")
	if resp != nil {
		resp.Body.Close()
	}
	if err == nil || calls.Load() != 21 {
		t.Fatalf("expected initial request plus 20 redirects; got %d requests, error %v", calls.Load(), err)
	}
}

func TestBlockedInvalidUTF8IsCachedWithoutRepair(t *testing.T) {
	raw := append([]byte(`{"data":[{"claim_id":"blocked","junk":"`), 255)
	raw = append(raw, []byte(`"}]}`)...)
	calls := 0
	s := Server{Cache: Cache{Dir: t.TempDir()}, HTTPClient: &http.Client{Transport: roundTrip(func(r *http.Request) (*http.Response, error) {
		calls++
		return &http.Response{StatusCode: 200, Header: http.Header{}, Body: io.NopCloser(bytes.NewReader(raw))}, nil
	})}}
	for i := 0; i < 2; i++ {
		if len(s.blocked(context.Background())) != 0 {
			t.Fatal("malformed provider JSON was repaired")
		}
	}
	if calls != 1 {
		t.Fatal("successful binary response must remain cached", calls)
	}
	cached, ok := s.Cache.Get("list_blocked")
	if !ok {
		t.Fatal("missing raw response cache")
	}
	decoded, ok := decodeBlockedCache(cached)
	if !ok || !bytes.Equal(decoded, raw) {
		t.Fatal("cached bytes changed")
	}
}
func TestProviderJSONDepthAndSurrogates(t *testing.T) {
	for _, c := range []struct {
		body  string
		valid bool
	}{{`{"data":[],"junk":"\ud800"}`, false}, {`{"data":[],"junk":"\ud800\udc00"}`, true}, {`{"data":[],"junk":"\\ud800"}`, true}, {strings.Repeat("[", 511) + "0" + strings.Repeat("]", 511), true}, {strings.Repeat("[", 512) + "0" + strings.Repeat("]", 512), false}} {
		if got := validProviderJSON([]byte(c.body)); got != c.valid {
			t.Fatal("JSON validation mismatch", got, c.valid)
		}
	}
}

func TestConnectionBudgetSeparatesHandshakeAndTransfer(t *testing.T) {
	req := httptest.NewRequest("GET", "https://example.invalid/", nil)
	bounded, finish := connectionBudget(req, 10*time.Millisecond)
	trace := httptrace.ContextClientTrace(bounded.Context())
	trace.GetConn("example.invalid:443")
	select {
	case <-bounded.Context().Done():
	case <-time.After(time.Second):
		t.Fatal("connection budget did not cancel")
	}
	finish()
	bounded, finish = connectionBudget(req, 5*time.Millisecond)
	defer finish()
	trace = httptrace.ContextClientTrace(bounded.Context())
	trace.GetConn("example.invalid:443")
	trace.GotConn(httptrace.GotConnInfo{})
	select {
	case <-bounded.Context().Done():
		t.Fatal("successful connection timer cancelled response transfer")
	case <-time.After(15 * time.Millisecond):
	}
}
