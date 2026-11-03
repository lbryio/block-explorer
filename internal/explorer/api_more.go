package explorer

import (
	"encoding/json"
	"fmt"
	"math/big"
	"math/rand/v2"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"
)

func placeholders(n int) string { return strings.TrimSuffix(strings.Repeat("?,", n), ",") }

var bcNumber = regexp.MustCompile(`^(?:[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+))?$`)

func validBCString(s string) bool { return bcNumber.MatchString(s) }

func decimal(v any) *big.Rat {
	r := new(big.Rat)
	if _, ok := r.SetString(text(v)); !ok {
		return new(big.Rat)
	}
	return r
}
func truncated(r *big.Rat, places int) string {
	scale := new(big.Int).Exp(big.NewInt(10), big.NewInt(int64(places)), nil)
	n := new(big.Int).Mul(r.Num(), scale)
	n.Quo(n, r.Denom())
	sign := ""
	if n.Sign() < 0 {
		sign = "-"
		n.Abs(n)
	}
	s := n.String()
	if places == 0 {
		return sign + s
	}
	for len(s) <= places {
		s = "0" + s
	}
	return sign + s[:len(s)-places] + "." + s[len(s)-places:]
}
func phpString(v any) string {
	if v == nil {
		return "0"
	}
	if f, ok := v.(float64); ok {
		s := strconv.FormatFloat(f, 'G', 14, 64)
		if i := strings.IndexByte(s, 'E'); i >= 0 {
			mantissa := s[:i]
			if !strings.Contains(mantissa, ".") {
				mantissa += ".0"
			}
			exp, _ := strconv.Atoi(s[i+1:])
			s = mantissa + fmt.Sprintf("E%+d", exp)
		}
		return s
	}
	return text(v)
}
func (s *Server) remainingAPI(w http.ResponseWriter, r *http.Request) bool {
	path := r.URL.Path
	q := func(sql string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), sql, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	if path == "/api/v1/charts/inflation" {
		if r.Method != "GET" {
			jsonError(w, 400, "Invalid HTTP request method.")
			return true
		}
		if b, ok := s.cachedArray("inflation_chart"); ok {
			sendJSON(w, 200, struct {
				Success bool            `json:"success"`
				Data    json.RawMessage `json:"data"`
			}{true, b})
			return true
		}
		highest, ok := q("SELECT height FROM block WHERE confirmations > 0 ORDER BY height DESC LIMIT 1")
		if !ok {
			return true
		}
		if len(highest) == 0 {
			clientError(w, r, 404, "Not Found")
			return true
		}
		h := int(number(highest[0]["height"]))
		args := []any{}
		for i := 0; i <= h; i += 1000 {
			args = append(args, i)
		}
		if h%1000 != 0 {
			args = append(args, h)
		}
		samples, ok := q("SELECT height,block_time FROM block WHERE height IN ("+placeholders(len(args))+") AND confirmations > 0 ORDER BY height ASC", args...)
		if !ok {
			return true
		}
		recent, ok := q("SELECT height,block_time FROM block WHERE confirmations > 0 ORDER BY height DESC LIMIT 101")
		if !ok {
			return true
		}
		for a, b := 0, len(recent)-1; a < b; a, b = a+1, b-1 {
			recent[a], recent[b] = recent[b], recent[a]
		}
		data := struct {
			Samples  []Row `json:"samples"`
			Recent   []Row `json:"recent"`
			Last     int   `json:"last_reward_block"`
			Interval int   `json:"future_sample_interval"`
		}{orderedRows(samples, "height", "block_time"), orderedRows(recent, "height", "block_time"), 4071017, 1000}
		b, e := phpJSON(data)
		if e == nil {
			s.Cache.Put("inflation_chart", b, 10*time.Minute)
		}
		sendJSON(w, 200, struct {
			Success bool `json:"success"`
			Data    any  `json:"data"`
		}{true, data})
		return true
	}
	if path == "/api/v1/supply" {
		if b, ok := s.cachedArray("utxo_supply"); ok {
			sendJSON(w, 200, struct {
				Success bool            `json:"success"`
				Supply  json.RawMessage `json:"utxosupply"`
			}{true, b})
			return true
		}
		total, ok := q("SELECT SUM(balance) AS balance FROM address")
		if !ok {
			return true
		}
		addresses := []any{"rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8", "r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t", "rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3", "bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt", "bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2", "bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD", "bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz", "bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc", "bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393", "bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH", "bCrboXVztuSbZzVToCWSsu1pEr2oxKHu9v", "bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h", "bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN"}
		reserved, ok := q("SELECT SUM(balance) AS balance FROM address WHERE address IN ("+placeholders(len(addresses))+")", addresses...)
		if !ok {
			return true
		}
		t, rv := "0", "0"
		if len(total) > 0 {
			t = phpString(total[0]["balance"])
		}
		if len(reserved) > 0 {
			rv = phpString(reserved[0]["balance"])
		}
		if !validBCString(t) || !validBCString(rv) {
			internalError(w)
			return true
		}
		data := struct {
			Total       string `json:"total"`
			Circulating string `json:"circulating"`
		}{t, truncated(new(big.Rat).Sub(decimal(t), decimal(rv)), 8)}
		b, e := phpJSON(data)
		if e == nil {
			s.Cache.Put("utxo_supply", b, time.Hour)
		}
		sendJSON(w, 200, struct {
			Success bool `json:"success"`
			Supply  any  `json:"utxosupply"`
		}{true, data})
		return true
	}
	if strings.HasPrefix(path, "/api/v1/address/") && strings.HasSuffix(path, "/utxo") {
		addr := strings.TrimSuffix(strings.TrimPrefix(path, "/api/v1/address/"), "/utxo")
		if addr == "" || strings.Contains(addr, "/") {
			return false
		}
		parts := strings.Split(addr, ",")
		args := make([]any, len(parts))
		for i, p := range parts {
			args[i] = p
		}
		addresses, ok := q("SELECT id,address FROM address WHERE address IN ("+placeholders(len(args))+")", args...)
		if !ok {
			return true
		}
		if len(addresses) == 0 {
			jsonError(w, 404, "No base58 address matching the specified parameter was found.")
			return true
		}
		args = nil
		conditions := []string{}
		for _, a := range addresses {
			args = append(args, a["id"])
			conditions = append(conditions, "JSON_CONTAINS(O.address_list, JSON_QUOTE(?))")
		}
		for _, a := range addresses {
			args = append(args, a["address"])
		}
		rows, ok := q("SELECT T.hash AS transaction_hash,O.vout,O.value,O.address_list,O.script_pub_key_asm,O.script_pub_key_hex,O.type,O.required_signatures,B.confirmations FROM transaction T JOIN output O ON O.transaction_id = T.id JOIN block B ON B.hash = T.block_hash_id WHERE O.transaction_id IN (SELECT TA.transaction_id FROM transaction_address TA WHERE TA.address_id IN ("+placeholders(len(addresses))+")) AND ("+strings.Join(conditions, " OR ")+") AND O.is_spent <> 1 ORDER BY T.transaction_time ASC", args...)
		if !ok {
			return true
		}
		out := make([]Row, 0, len(rows))
		for _, row := range rows {
			if !validBCString(phpString(row["value"])) {
				internalError(w)
				return true
			}
			var list any
			json.Unmarshal([]byte(text(row["address_list"])), &list)
			atomic := new(big.Rat).Mul(decimal(phpString(row["value"])), big.NewRat(100000000, 1))
			value, _ := strconv.ParseInt(truncated(atomic, 0), 10, 64)
			out = append(out, Row{"transaction_hash": row["transaction_hash"], "output_index": row["vout"], "value": value, "addresses": list, "script": row["script_pub_key_asm"], "script_hex": row["script_pub_key_hex"], "script_type": row["type"], "required_signatures": int(number(row["required_signatures"])), "spent": false, "confirmations": int(number(row["confirmations"]))})
		}
		sendJSON(w, 200, struct {
			Success bool  `json:"success"`
			UTXO    []Row `json:"utxo"`
		}{true, orderedRows(out, "transaction_hash", "output_index", "value", "addresses", "script", "script_hex", "script_type", "required_signatures", "spent", "confirmations")})
		return true
	}
	if path == "/api/v1/claims/browse" {
		before, after := max(0, queryInt(r, "before")), queryInt(r, "after")
		sort := phpTrim(queryString(r, "sort"))
		order := "DESC"
		var randomStart int
		if sort == "oldest" || sort == "random" {
			order = "ASC"
		}
		if sort == "random" {
			last, ok := q("SELECT id FROM claim ORDER BY id DESC LIMIT 1")
			if !ok {
				return true
			}
			if len(last) > 0 {
				maxID := int(number(last[0]["id"]))
				if maxID > 0 {
					randomStart = rand.IntN(maxID) + 1
					if s.RandomInt != nil {
						randomStart = s.RandomInt(maxID) + 1
					}
				}
			}
		}
		where := "Claims.thumbnail_url IS NOT NULL AND Claims.thumbnail_url != '' AND Claims.is_filtered != 1"
		args := []any{}
		if after > 0 {
			where += " AND Claims.id > ?"
			args = append(args, after)
			order = "ASC"
		} else if before > 0 {
			where += " AND Claims.id < ?"
			args = append(args, before)
			order = "DESC"
		} else if randomStart > 0 {
			where += " AND Claims.id >= ?"
			args = append(args, randomStart)
		}
		if phpTrim(queryString(r, "nsfw")) != "true" {
			where += " AND Claims.is_nsfw != 1"
		}
		rows, ok := q(fmt.Sprintf("SELECT Claims.*,Publisher.name AS publisher FROM claim Claims LEFT JOIN claim Publisher ON Publisher.claim_id = Claims.publisher_id WHERE %s ORDER BY Claims.id %s LIMIT 48", where, order), args...)
		if !ok {
			return true
		}
		if rows == nil {
			rows = []Row{}
		}
		sendJSON(w, 200, struct {
			Success bool  `json:"success"`
			Claims  []Row `json:"claims"`
			Total   int   `json:"total"`
		}{true, claimEntities(rows), 23000000})
		return true
	}
	return false
}
