package explorer

import (
	"context"
	"explorer/webroot"
	"fmt"
	"io/fs"
	"log/slog"
	"net/http"
	"path"
	"strings"
	"time"
)

type requestStartKey struct{}

type Server struct {
	Logger     *slog.Logger
	RandomInt  func(int) int
	HTTPClient *http.Client
	Store      Store
	Cache      Cache
	Now        func() time.Time
}

func (s *Server) now() time.Time {
	if s.Now != nil {
		return s.Now().UTC()
	}
	return time.Now().UTC()
}
func (s *Server) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	r = r.WithContext(context.WithValue(r.Context(), requestStartKey{}, time.Now()))
	if r.Method == "HEAD" {
		w = headWriter{w}
	}
	// Serve only real files under the production webroot. Missing files continue
	// through application routing, as they do in the production front controller.
	name := strings.TrimPrefix(path.Clean("/"+r.URL.Path), "/")
	if info, e := fs.Stat(webroot.Files, name); e == nil && !info.IsDir() {
		http.ServeFileFS(w, r, webroot.Files, name)
		return
	}
	match := matchRoute(r.URL.EscapedPath(), r.Method)
	if match.Action == "badmethod" {
		badMethodError(w, r)
		return
	}
	r = routedRequest(r, match)
	if s.api(w, r) {
		return
	}
	s.page(w, r)
}
func (s *Server) api(w http.ResponseWriter, r *http.Request) bool {
	path := r.URL.Path
	query := func(q string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), q, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	if s.remainingAPI(w, r) {
		return true
	}
	switch path {
	case "/api/v1/status":
		rows, ok := query("SELECT height,difficulty FROM block ORDER BY height DESC LIMIT 1")
		if !ok {
			return true
		}
		var height any = 0
		difficulty := 0.0
		if len(rows) > 0 {
			height = rows[0]["height"]
			difficulty = number(rows[0]["difficulty"])
		}
		sendJSON(w, 200, struct {
			Success bool `json:"success"`
			Status  any  `json:"status"`
		}{true, struct {
			Height     any    `json:"height"`
			Difficulty string `json:"difficulty"`
			Price      string `json:"price"`
			Hashrate   string `json:"hashrate"`
		}{height, fixed(difficulty, 2), "N/A", "N/A"}})
	case "/api/v1/recentblocks", "/api/v1/realtime/blocks":
		q := "SELECT height AS Height,block_time AS BlockTime,tx_count AS TransactionCount FROM block ORDER BY height DESC LIMIT 10"
		if path == "/api/v1/recentblocks" {
			q = "SELECT difficulty AS Difficulty,hash AS Hash,height AS Height,block_time AS BlockTime,block_size AS BlockSize,tx_count AS TransactionCount FROM block ORDER BY height DESC LIMIT 6"
		}
		rows, ok := query(q)
		if !ok {
			return true
		}
		if path == "/api/v1/recentblocks" {
			for _, row := range rows {
				row["Difficulty"] = fixed(row["Difficulty"], 2)
			}
		}
		keys := []string{"Height", "BlockTime", "TransactionCount"}
		if path == "/api/v1/recentblocks" {
			keys = []string{"Difficulty", "Hash", "Height", "BlockTime", "BlockSize", "TransactionCount"}
		}
		sendJSON(w, 200, struct {
			Success bool  `json:"success"`
			Blocks  []Row `json:"blocks"`
		}{true, orderedRows(rows, keys...)})
	case "/api/v1/realtime/tx":
		rows, ok := query("SELECT id,value AS Value,hash AS Hash,input_count AS InputCount,output_count AS OutputCount,transaction_time AS TxTime FROM transaction ORDER BY transaction_time DESC LIMIT 10")
		if ok {
			sendJSON(w, 200, struct {
				Success bool  `json:"success"`
				Txs     []Row `json:"txs"`
			}{true, orderedRows(rows, "id", "Value", "Hash", "InputCount", "OutputCount", "TxTime")})
		}
	default:
		if strings.HasPrefix(path, "/api/v1/address/") && strings.HasSuffix(path, "/balance") {
			addr := strings.TrimSuffix(strings.TrimPrefix(path, "/api/v1/address/"), "/balance")
			if addr == "" || strings.Contains(addr, "/") {
				return false
			}
			rows, ok := query("SELECT id,balance FROM address WHERE address = ? LIMIT 1", addr)
			if !ok {
				return true
			}
			if len(rows) == 0 {
				jsonError(w, 400, "Could not find address.")
				return true
			}
			sendJSON(w, 200, struct {
				Success bool `json:"success"`
				Balance any  `json:"balance"`
			}{true, struct {
				Confirmed   any `json:"confirmed"`
				Unconfirmed int `json:"unconfirmed"`
			}{rows[0]["balance"], 0}})
			return true
		}
		if strings.HasPrefix(path, "/api/v1/charts/blocksize/") {
			period := strings.TrimPrefix(path, "/api/v1/charts/blocksize/")
			if strings.Contains(period, "/") {
				return false
			}
			if r.Method != "GET" {
				jsonError(w, 400, "Invalid HTTP request method.")
				return true
			}
			durations := map[string]time.Duration{"24h": 24 * time.Hour, "72h": 72 * time.Hour, "168h": 168 * time.Hour, "30d": 30 * 24 * time.Hour, "90d": 90 * 24 * time.Hour}
			start := s.now()
			d, valid := durations[period]
			if period == "1y" {
				start = start.AddDate(-1, 0, 0)
				valid = true
			} else {
				start = start.Add(-d)
			}
			if !valid {
				jsonError(w, 400, "Invalid time period specified.")
				return true
			}
			format, layout := "%Y-%m-%d", "2006-01-02"
			if strings.HasSuffix(period, "h") {
				format, layout = "%Y-%m-%d %H:00:00", "2006-01-02 15:00:00"
			}
			q := fmt.Sprintf("SELECT AVG(block_size) AS AvgBlockSize, DATE_FORMAT(FROM_UNIXTIME(block_time), '%s') AS TimePeriod FROM block WHERE DATE_FORMAT(FROM_UNIXTIME(block_time), '%s') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", format, format)
			rows, ok := query(q, start.Format(layout))
			if !ok {
				return true
			}
			var data any = []any{}
			if len(rows) > 0 {
				m := map[string]any{}
				for _, row := range rows {
					m[fmt.Sprint(row["TimePeriod"])] = struct {
						Avg float64 `json:"AvgBlockSize"`
					}{number(row["AvgBlockSize"])}
				}
				data = m
			}
			sendJSON(w, 200, struct {
				Success bool `json:"success"`
				Data    any  `json:"data"`
			}{true, data})
			return true
		}
		return false
	}
	return true
}
