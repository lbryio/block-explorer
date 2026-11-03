package explorer

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"errors"
	"io"
	"net"
	"net/http"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"
)

const blockedListURL = "https://api.odysee.com/file/list_blocked?with_claim_id=true"

func blockedHTTPClient() *http.Client {
	return &http.Client{CheckRedirect: func(req *http.Request, via []*http.Request) error {
		req.Header.Del("Referer")
		if len(via) > 20 {
			return errors.New("maximum 20 redirects followed")
		}
		return nil
	}, Timeout: 15 * time.Second, Transport: &http.Transport{ForceAttemptHTTP2: true, DisableCompression: true, Proxy: http.ProxyFromEnvironment, DialContext: (&net.Dialer{Timeout: 5 * time.Second}).DialContext, TLSHandshakeTimeout: 5 * time.Second}}
}
func (s *Server) blocked(ctx context.Context) map[string]bool {
	started := time.Now()
	cacheHit := false
	fetchFailed := false
	status := 0
	if s.Logger != nil {
		defer func() {
			s.Logger.InfoContext(ctx, "blocked_list", "cache_hit", cacheHit, "fetch_failed", fetchFailed, "http_status", status, "duration_ms", float64(time.Since(started))/float64(time.Millisecond))
		}()
	}
	var body []byte
	cached, hit := s.Cache.Get("list_blocked")
	if hit {
		var ok bool
		body, ok = decodeBlockedCache(cached)
		if ok {
			cacheHit = true
		} else {
			hit = false
		}
	}

	if !hit {
		client := s.HTTPClient
		if client == nil {
			client = blockedHTTPClient()
			defer client.CloseIdleConnections()
		}
		req, e := http.NewRequestWithContext(ctx, "GET", blockedListURL, nil)
		if e != nil {
			return nil
		}
		req.Header.Set("User-Agent", "")
		req.Header.Set("Accept", "*/*")
		req, finishConnectBudget := connectionBudget(req, 5*time.Second)
		defer finishConnectBudget()
		resp, e := client.Do(req)
		if e != nil {
			fetchFailed = true
			return nil
		}
		status = resp.StatusCode
		defer resp.Body.Close()
		body, e = io.ReadAll(resp.Body)
		if e != nil {
			fetchFailed = true
			return nil
		}
		var b []byte
		if utf8.Valid(body) {
			b, _ = json.Marshal(string(body))
		} else {
			b, _ = json.Marshal(struct {
				Raw []byte `json:"raw_base64"`
			}{body})
		}
		s.Cache.Put("list_blocked", b, 10*time.Minute)
	}
	return parseBlocked(body)
}

const blockedTrue = "\xfftrue"
const blockedFalse = "\xfffalse"
const blockedNull = "\xffnull"

func parseBlocked(body []byte) map[string]bool {
	if !validProviderJSON(body) {
		return nil
	}
	var root map[string]json.RawMessage
	if json.Unmarshal(body, &root) != nil {
		return nil
	}
	var entries []json.RawMessage
	if json.Unmarshal(root["data"], &entries) != nil {
		return nil
	}
	out := map[string]bool{}
	for _, item := range entries {
		var fields map[string]json.RawMessage
		if json.Unmarshal(item, &fields) != nil {
			out[blockedNull] = true
			continue
		}
		raw := fields["claim_id"]
		if len(raw) == 0 || string(raw) == "null" {
			out[blockedNull] = true
			continue
		}
		if string(raw) == "true" {
			out[blockedTrue] = true
			continue
		}
		if string(raw) == "false" {
			out[blockedFalse] = true
			continue
		}
		var id string
		if json.Unmarshal(raw, &id) == nil {
			out[id] = true
			continue
		}
		var n json.Number
		if json.Unmarshal(raw, &n) == nil {
			out[string(n)] = true
		}
	}
	return out
}
func isBlockedID(id string, list map[string]bool) bool {
	if list[id] || list[blockedTrue] && truth(id) || list[blockedFalse] && !truth(id) || list[blockedNull] && id == "" {
		return true
	}
	a := strings.Trim(id, " \t\n\r\f\v")
	if !numericPattern.MatchString(a) {
		return false
	}
	ai, ae := strconv.ParseInt(a, 10, 64)
	af, _ := strconv.ParseFloat(a, 64)
	for key := range list {
		b := strings.Trim(key, " \t\n\r\f\v")
		if !numericPattern.MatchString(b) {
			continue
		}
		bi, be := strconv.ParseInt(b, 10, 64)
		if ae == nil && be == nil {
			if ai == bi {
				return true
			}
		} else {
			bf, _ := strconv.ParseFloat(b, 64)
			if af == bf {
				return true
			}
		}
	}
	return false
}

func markBlocked(rows []Row, list map[string]bool) {
	for _, row := range rows {
		blocked := isBlockedID(text(row["claim_id"]), list)
		if publisher := text(row["publisher_id"]); truth(publisher) {
			blocked = blocked || isBlockedID(publisher, list)
		}
		row["isBlocked"] = blocked
		if blocked {
			row["thumbnail_url"] = nil
		}
	}
}

func decodeBlockedCache(cached []byte) ([]byte, bool) {
	trimmed := bytes.TrimSpace(cached)
	if len(trimmed) > 0 && trimmed[0] == '"' {
		var raw string
		if json.Unmarshal(cached, &raw) == nil {
			return []byte(raw), true
		}
	}
	var wrapper map[string]json.RawMessage
	if json.Unmarshal(cached, &wrapper) != nil {
		return nil, false
	}
	var raw string
	field := wrapper["raw_base64"]
	if len(field) == 0 || field[0] != '"' || json.Unmarshal(field, &raw) != nil {
		return nil, false
	}
	decoded, e := base64.StdEncoding.DecodeString(raw)
	return decoded, e == nil
}

// PHP json_decode rejects malformed UTF-8 and unpaired UTF-16 escapes rather
// than replacing them. Validate these before the Go decoder sees the payload.
func validProviderJSON(body []byte) bool {
	if !utf8.Valid(body) || !json.Valid(body) {
		return false
	}
	quoted := false
	depth := 0
	for i := 0; i < len(body); i++ {
		c := body[i]
		if c == '"' {
			quoted = !quoted
			continue
		}
		if !quoted {
			if c == '{' || c == '[' {
				depth++
				if depth >= 512 {
					return false
				}
			} else if c == '}' || c == ']' {
				depth--
			}
			continue
		}
		if c != '\\' {
			continue
		}
		i++
		if body[i] != 'u' {
			continue
		}
		code, _ := strconv.ParseUint(string(body[i+1:i+5]), 16, 16)
		i += 4
		if code >= 0xdc00 && code <= 0xdfff {
			return false
		}
		if code >= 0xd800 && code <= 0xdbff {
			if i+6 >= len(body) || body[i+1] != '\\' || body[i+2] != 'u' {
				return false
			}
			low, e := strconv.ParseUint(string(body[i+3:i+7]), 16, 16)
			if e != nil || low < 0xdc00 || low > 0xdfff {
				return false
			}
			i += 6
		}
	}
	return true
}
