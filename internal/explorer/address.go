package explorer

import (
	"html/template"
	"math/big"
	"net/http"
	"net/url"
	"regexp"
	"strings"
)

var unixDigits = regexp.MustCompile(`^[+-]?[0-9]+$`)

func safeURL(v any) template.URL {
	s := phpTrim(text(v))
	u, e := url.Parse(s)
	if e != nil {
		return template.URL("#")
	}
	switch strings.ToLower(u.Scheme) {
	case "", "http", "https", "ftp", "lbry", "mailto":
		return template.URL(s)
	}
	return template.URL("#")
}
func (s *Server) addressPage(w http.ResponseWriter, r *http.Request) {
	addr := firstArgument(r, "/address")
	if addr == "" || addr == "0" {
		redirect(w, r, "/", 302)
		return
	}
	query := func(q string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), q, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	rows, ok := query("SELECT * FROM address WHERE address = ? LIMIT 1", addr)
	if !ok {
		return
	}
	data := pageData{Title: "Address " + addr, Address: Row{"address": addr}, NumPages: 1, PageLimit: 50, CurrentPage: queryInt(r, "page")}
	if len(rows) == 0 {
		if len(addr) != 34 {
			redirect(w, r, "/", 302)
			return
		}
	} else {
		data.Address = rows[0]
		tas, ok := query("SELECT * FROM transaction_address WHERE address_id = ?", data.Address["id"])
		if !ok {
			return
		}
		data.NumRecords = len(tas)
		offset := 0
		if queryString(r, "all") == "true" {
			data.PageLimit = len(tas)
			data.CurrentPage = 1
			data.NumPages = 1
		} else {
			data.CurrentPage, data.NumPages, data.Pagination = paginate(len(tas), 50, data.CurrentPage)
			offset = (data.CurrentPage - 1) * 50
		}
		data.RecentTxs, ok = query("SELECT T.id,T.hash,T.input_count,T.output_count,T.block_hash_id,TA.debit_amount,TA.credit_amount,B.height,B.confirmations,IFNULL(T.transaction_time,T.created_at) AS transaction_time FROM transaction T LEFT JOIN block B ON T.block_hash_id = B.hash RIGHT JOIN (SELECT transaction_id,debit_amount,credit_amount FROM transaction_address WHERE address_id = ?) TA ON TA.transaction_id = T.id ORDER BY transaction_time DESC LIMIT ?, ?", data.Address["id"], offset, data.PageLimit)
		if !ok {
			return
		}
		for _, tx := range data.RecentTxs {
			if !unixDigits.MatchString(text(tx["transaction_time"])) {
				internalError(w)
				return
			}
		}
		for _, ta := range tas {
			data.TotalReceived += number(ta["credit_amount"])
			data.TotalSent += number(ta["debit_amount"])
		}
		data.BalanceAmount = data.TotalReceived - data.TotalSent
	}
	data.Begin = (data.CurrentPage-1)*data.PageLimit + 1
	data.End = min(data.NumRecords, data.Begin+data.PageLimit-1)
	s.render(w, r, "address", data)
}

var ownedAddresses = map[string]bool{"rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8": true, "rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3": true, "r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t": true, "bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt": true, "bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2": true, "bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD": true, "bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz": true, "bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393": true, "bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH": true, "bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h": true, "bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc": true, "bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN": true, "bG1fEEqDVepDy3AbvM8outQ3FQUu76aDot": true}

const inflationChartVersion = 1785112059

func (s *Server) statsPage(w http.ResponseWriter, r *http.Request) {
	rows, e := s.Store.Query(r.Context(), "SELECT * FROM address WHERE address <> ? ORDER BY balance DESC LIMIT 500", "bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf")
	if e != nil {
		internalError(w)
		return
	}
	total := new(big.Rat)
	maxBalance := new(big.Rat)
	for _, item := range rows {
		if item["first_seen"] == nil {
			internalError(w)
			return
		}
		if !validBCString(phpString(item["balance"])) {
			internalError(w)
			return
		}
		balance := decimal(phpString(item["balance"]))
		total = decimal(truncated(new(big.Rat).Add(total, balance), 8))
		if balance.Cmp(maxBalance) > 0 {
			maxBalance = balance
		}
	}
	if len(rows) > 0 && (total.Sign() == 0 || maxBalance.Sign() == 0) {
		internalError(w)
		return
	}
	for _, item := range rows {
		balance := decimal(phpString(item["balance"]))
		item["Top500Percent"] = number(truncated(new(big.Rat).Quo(balance, total), 8)) * 100
		item["MinMaxPercent"] = number(truncated(new(big.Rat).Quo(balance, maxBalance), 8)) * 100
	}
	s.render(w, r, "stats", pageData{Title: "Stats & Rich List", RichList: rows, InflationChartVersion: inflationChartVersion})
}
