package explorer

import (
	"context"
	"database/sql"
	"errors"
	"log/slog"
	"net/http"
	"time"

	"github.com/go-sql-driver/mysql"
)

// ObservedStore records query cost without logging SQL, arguments, credentials,
// claim metadata or database error messages. It introduces no DB operations.
type ObservedStore struct {
	Store  Store
	Logger *slog.Logger
	Stats  func() sql.DBStats
}

func (s ObservedStore) Query(ctx context.Context, q string, args ...any) ([]Row, error) {
	started := time.Now()
	rows, err := s.Store.Query(ctx, q, args...)
	if s.Logger != nil {
		code := 0
		var mysqlErr *mysql.MySQLError
		if errors.As(err, &mysqlErr) {
			code = int(mysqlErr.Number)
		}
		attrs := []any{"duration_ms", float64(time.Since(started)) / float64(time.Millisecond), "rows", len(rows), "success", err == nil, "mysql_error_code", code}
		if s.Stats != nil {
			stats := s.Stats()
			attrs = append(attrs, "pool_open", stats.OpenConnections, "pool_in_use", stats.InUse, "pool_wait_count", stats.WaitCount, "pool_wait_ms", stats.WaitDuration.Milliseconds())
		}
		s.Logger.InfoContext(ctx, "db_query", attrs...)
	}
	return rows, err
}

type observedResponse struct {
	http.ResponseWriter
	status int
	bytes  int64
}

func (w *observedResponse) Unwrap() http.ResponseWriter { return w.ResponseWriter }
func (w *observedResponse) WriteHeader(status int) {
	if w.status != 0 {
		return
	}
	w.status = status
	w.ResponseWriter.WriteHeader(status)
}
func (w *observedResponse) Write(b []byte) (int, error) {
	if w.status == 0 {
		w.WriteHeader(200)
	}
	n, e := w.ResponseWriter.Write(b)
	w.bytes += int64(n)
	return n, e
}

// AccessLog uses the matched action instead of raw query parameters or paths.
// It adds no headers or public monitoring routes.
func AccessLog(next http.Handler, logger *slog.Logger) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		started := time.Now()
		response := &observedResponse{ResponseWriter: w}
		next.ServeHTTP(response, r)
		status := response.status
		if status == 0 {
			status = 200
		}
		if logger != nil {
			logger.InfoContext(r.Context(), "http_request", "method", r.Method, "action", matchRoute(r.URL.EscapedPath(), r.Method).Action, "status", status, "bytes", response.bytes, "duration_ms", float64(time.Since(started))/float64(time.Millisecond))
		}
	})
}
