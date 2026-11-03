package explorer

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

type failedStore struct{}

func (failedStore) Query(context.Context, string, ...any) ([]Row, error) {
	return nil, errors.New("private-password-or-SQL-must-not-be-logged")
}
func TestObservabilityPreservesResponseAndRedactsInputs(t *testing.T) {
	var log bytes.Buffer
	logger := slog.New(slog.NewJSONHandler(&log, nil))
	observed := ObservedStore{Store: &fakeStore{rows: []Row{}}, Logger: logger, Stats: func() sql.DBStats { return sql.DBStats{OpenConnections: 1, WaitCount: 2} }}
	app := &Server{Store: observed}
	w := httptest.NewRecorder()
	AccessLog(app, logger).ServeHTTP(w, httptest.NewRequest("GET", "/api/v1/status?secret=do-not-log", nil))
	if w.Code != 200 || w.Body.String() != `{"success":true,"status":{"height":0,"difficulty":"0.00","price":"N\/A","hashrate":"N\/A"}}` {
		t.Fatal(w.Code, w.Body.String())
	}
	if strings.Contains(log.String(), "secret") || strings.Contains(log.String(), "SELECT") || strings.Contains(log.String(), "do-not-log") {
		t.Fatal("private input in logs")
	}
	for _, line := range strings.Split(strings.TrimSpace(log.String()), "\n") {
		var item map[string]any
		if e := json.Unmarshal([]byte(line), &item); e != nil {
			t.Fatal(e)
		}
	}
	failed := ObservedStore{Store: failedStore{}, Logger: logger}
	failed.Query(context.Background(), "SELECT private", "password")
	if strings.Contains(log.String(), "private-password") || strings.Contains(log.String(), "SELECT private") {
		t.Fatal("database error text or SQL leaked")
	}
}
func TestAccessLogHeadResponse(t *testing.T) {
	app := AccessLog(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { w.Header().Set("X-Test", "kept"); w.WriteHeader(302) }), nil)
	w := httptest.NewRecorder()
	app.ServeHTTP(w, httptest.NewRequest("HEAD", "/", nil))
	if w.Code != 302 || w.Header().Get("X-Test") != "kept" || w.Body.Len() != 0 {
		t.Fatal(w.Code, w.Header(), w.Body.String())
	}
}
