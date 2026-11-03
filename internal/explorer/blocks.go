package explorer

import (
	"net/http"
	"regexp"
	"strconv"
	"strings"
)

var phpIntPrefix = regexp.MustCompile(`^[\t\n\r\f\v ]*([+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)`)

func phpInt(s string) int {
	m := phpIntPrefix.FindStringSubmatch(s)
	if len(m) < 2 {
		return 0
	}
	if !strings.ContainsAny(m[1], ".eE") {
		v, _ := strconv.ParseInt(m[1], 10, 64)
		return int(v)
	}
	v, _ := strconv.ParseFloat(m[1], 64)
	if v >= 9223372036854775807 {
		return int(^uint(0) >> 1)
	}
	if v <= -9223372036854775808 {
		return -int(^uint(0)>>1) - 1
	}
	return int(v)
}
func (s *Server) blocksPage(w http.ResponseWriter, r *http.Request) {
	q := func(sql string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), sql, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	rest := strings.TrimPrefix(r.URL.Path, "/blocks")
	rest = strings.TrimPrefix(rest, "/")
	if rest != "" {
		height := phpInt(firstArgument(r, "/blocks"))
		if height < 0 {
			redirect(w, r, "/", 302)
			return
		}
		rows, ok := q("SELECT * FROM block WHERE height = ? LIMIT 1", height)
		if !ok {
			return
		}
		if len(rows) == 0 {
			redirect(w, r, "/", 302)
			return
		}
		block := rows[0]
		txs, ok := q("SELECT id,value,input_count,output_count,hash,version FROM transaction WHERE block_hash_id = ?", block["hash"])
		if !ok {
			return
		}
		last, ok := q("SELECT height FROM block ORDER BY height DESC LIMIT 1")
		if !ok {
			return
		}
		confirmations := int64(0)
		if len(last) > 0 {
			confirmations = int64(number(last[0]["height"]) - number(block["height"]) + 1)
		}
		s.render(w, r, "block", pageData{Title: "Block Height " + text(block["height"]), Block: block, Txs: txs, Confirmations: confirmations})
		return
	}
	count, ok := q("SELECT height AS Total FROM block ORDER BY id DESC LIMIT 1")
	if !ok {
		return
	}
	total := 0
	if len(count) > 0 {
		total = int(number(count[0]["Total"]))
	}
	if total == 0 {
		internalError(w)
		return
	}
	page, pages, pagination := paginate(total, 50, queryInt(r, "page"))
	last, ok := q("SELECT height FROM block ORDER BY height DESC LIMIT 1")
	if !ok {
		return
	}
	highest := Row{"height": 0}
	if len(last) > 0 {
		highest = last[0]
	}
	blocks := []Row{}
	if pages > 0 {
		blocks, ok = q("SELECT height,difficulty,block_size,nonce,block_time,tx_count FROM block ORDER BY height DESC LIMIT 50 OFFSET ?", (page-1)*50)
		if !ok {
			return
		}
	}
	begin := (page-1)*50 + 1
	s.render(w, r, "blocks", pageData{Title: "Blocks", Blocks: blocks, Highest: highest, NumRecords: total, NumPages: pages, CurrentPage: page, PageLimit: 50, Pagination: pagination, Begin: begin, End: min(total, begin+49)})
}
