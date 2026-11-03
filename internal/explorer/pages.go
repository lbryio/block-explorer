package explorer

import (
	"bytes"
	"embed"
	"encoding/json"
	"fmt"
	"html"
	"html/template"
	"math"
	"math/rand/v2"
	"net/http"
	"strconv"
	"strings"
	"time"
)

//go:embed templates/*.html
var templateFiles embed.FS

type pageData struct {
	Tx                                           Row
	Inputs, Outputs                              []ioView
	Fee                                          string
	Address                                      Row
	RecentTxs, RichList                          []Row
	BalanceAmount, TotalReceived, TotalSent      float64
	InflationChartVersion                        int64
	Claim                                        Row
	MoreClaims                                   []Row
	ClaimIsBlocked                               bool
	Title                                        string
	Analytics                                    bool
	RecentBlocks, RecentClaims, Blocks, Txs      []Row
	Highest, Block                               Row
	Confirmations                                int64
	NumRecords, NumPages, CurrentPage, PageLimit int
	Begin, End                                   int
	Pagination                                   pagination
	Claims                                       []Row
	Cards                                        []claimCard
}

func text(v any) string {
	if v == nil {
		return ""
	}
	if n, ok := v.(json.Number); ok {
		if strings.ContainsAny(string(n), ".eE") {
			return phpString(number(n))
		}
		return string(n)
	}
	if f, ok := v.(float64); ok {
		return phpString(f)
	}
	return fmt.Sprint(v)
}
func truth(v any) bool {
	if v == nil {
		return false
	}
	if b, ok := v.(bool); ok {
		return b
	}
	s := text(v)
	return s != "" && s != "0"
}
func fixed(v any, places int) string {
	return strconv.FormatFloat(roundPHP(number(v), places), 'f', places, 64)
}
func grouped(v any, places int) string {
	s := fixed(v, places)
	parts := strings.SplitN(s, ".", 2)
	sign := ""
	if strings.HasPrefix(parts[0], "-") {
		sign = "-"
		parts[0] = parts[0][1:]
	}
	n := parts[0]
	for i := len(n) - 3; i > 0; i -= 3 {
		n = n[:i] + "," + n[i:]
	}
	if len(parts) > 1 {
		n += "." + parts[1]
	}
	return sign + n
}
func amount(v any, separator string) string {
	s := fixed(v, 8)
	if separator != "" {
		s = strings.ReplaceAll(grouped(v, 8), ",", separator)
	}
	return strings.TrimSuffix(strings.TrimRight(s, "0"), ".")
}
func currency(v any) string {
	s := text(v)
	parts := strings.SplitN(s, ".", 2)
	if len(parts) == 1 {
		return grouped(v, 2)
	}
	left := grouped(parts[0], 0)
	if number("0."+parts[1]) == 0 {
		return left
	}
	right := parts[1] + "00"
	return left + "." + right[:2]
}
func timestamp(v any) time.Time {
	if str, ok := v.(string); ok {
		for _, layout := range []string{"2006-01-02 15:04:05", time.RFC3339Nano} {
			if t, e := time.ParseInLocation(layout, str, time.UTC); e == nil {
				return t
			}
		}
	}
	return time.Unix(int64(number(v)), 0).UTC()
}
func contentTag(r Row) string {
	ct := text(r["content_type"])
	for _, prefix := range []string{"audio", "video", "image"} {
		if strings.HasPrefix(ct, prefix) {
			return strings.ToUpper(prefix)
		}
	}
	if number(r["claim_type"]) == 2 {
		return "IDENTITY"
	}
	return ""
}
func autoThumb(r Row) string {
	name := text(r["name"])
	n := 5
	if number(r["claim_type"]) == 2 {
		n = 10
		if len(name) > 0 {
			name = name[1:]
		}
	} else if title := phpTrim(text(r["title"])); title != "" {
		name = title
	}
	if len(name) > n {
		name = name[:n]
	}
	var b strings.Builder
	for i := 0; i < len(name); i++ {
		c := name[i]
		if c >= 'a' && c <= 'z' {
			c -= 32
		}
		b.WriteByte(c)
	}
	return b.String()
}
func claimTitle(r Row) string {
	if number(r["claim_type"]) == 1 {
		return text(r["name"])
	}
	if phpTrim(text(r["title"])) != "" {
		return text(r["title"])
	}
	return ""
}
func lbryLink(r Row) template.URL {
	name := text(r["name"])
	if publisher := text(r["publisher"]); publisher != "" {
		name = publisher + "/" + name
	}
	return template.URL("lbry://" + name)
}
func thumbColor() string {
	return []string{"purple", "orange", "blue", "teal", "green", "yellow"}[rand.IntN(6)]
}
func (s *Server) functions() template.FuncMap {
	return template.FuncMap{
		"confirmationDelay": confirmationDelay, "safeURL": safeURL, "ownedAddress": func(v any) bool { return ownedAddresses[text(v)] }, "txClass": func(r Row) string {
			if number(r["debit_amount"]) > 0 {
				if number(r["credit_amount"]) > 0 {
					return "diff"
				}
				return "debit"
			}
			return "credit"
		}, "difference": func(a, b any) float64 { return number(a) - number(b) },
		"isset": func(row Row, key string) bool { return row[key] != nil }, "trim": func(v any) string { return phpTrim(text(v)) }, "language": func(v any) string {
			if text(v) == "en" {
				return "English"
			}
			return text(v)
		}, "claimCost": claimCost, "description": description, "publisherLink": func(v any) template.URL { return template.URL("lbry://" + text(v)) }, "claimDate": func(r Row) string {
			v := r["created_at"]
			if number(r["transaction_time"]) > 0 {
				v = r["transaction_time"]
			}
			return timestamp(v).Format("2 Jan 2006 15:04:05")
		},
		"text": text, "truth": truth, "num": number, "fixed": fixed, "grouped": grouped, "amount": amount, "currency": currency, "contentTag": contentTag, "autoThumb": autoThumb, "claimTitle": claimTitle, "lbryLink": lbryLink, "lbryHref": func(r Row) template.HTMLAttr {
			return template.HTMLAttr(`href="` + html.EscapeString(string(lbryLink(r))) + `"`)
		}, "publisherHref": func(v any) template.HTMLAttr {
			return template.HTMLAttr(`href="` + html.EscapeString("lbry://"+text(v)) + `"`)
		}, "thumbColor": thumbColor,
		"showThumbnail": func(r Row) bool { return !truth(r["is_nsfw"]) && phpTrim(text(r["thumbnail_url"])) != "" },
		"thumbnail": func(v any) string {
			return "https://thumbnails.odycdn.com/optimize/s:1280:720/quality:85/plain/" + text(v)
		},
		"kb":       func(v any) string { return phpString(roundPHP(number(v)/1024, 2)) + "KB" },
		"date":     func(v any, layout string) string { return timestamp(v).Format(layout) },
		"ago":      func(v any) string { return cakeAgo(timestamp(v), s.now()) },
		"humanAgo": func(v any) string { return humanAgo(timestamp(v), s.now()) },
		"add":      func(a, b any) int { return int(number(a) + number(b)) }, "sub": func(a, b any) int { return int(number(a) - number(b)) },
	}
}

func (s *Server) render(w http.ResponseWriter, r *http.Request, name string, data pageData) {
	data.Analytics = r.Host == "explorer.lbry.com"
	started := time.Now()
	if value, ok := r.Context().Value(requestStartKey{}).(time.Time); ok {
		started = value
	}
	functions := s.functions()
	functions["elapsed"] = func() int64 { return int64(math.Round(float64(time.Since(started)) / float64(time.Millisecond))) }
	t, e := template.New("layout").Funcs(functions).ParseFS(templateFiles, "templates/layout.html", "templates/header.html", "templates/pagination.html", "templates/claimbox.html", "templates/"+name+".html")
	if e != nil {
		http.Error(w, "Template error", 500)
		return
	}
	var b bytes.Buffer
	if e = t.ExecuteTemplate(&b, "layout", data); e != nil {
		http.Error(w, "Template execution error", 500)
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=UTF-8")
	w.Write(b.Bytes())
}
func (s *Server) page(w http.ResponseWriter, r *http.Request) {
	q := func(query string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), query, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	if r.URL.Path == "/qr" || strings.HasPrefix(r.URL.Path, "/qr/") {
		s.qr(w, r)
		return
	}
	if r.URL.Path == "/address" || strings.HasPrefix(r.URL.Path, "/address/") {
		s.addressPage(w, r)
		return
	}
	if r.URL.Path == "/stats" {
		s.statsPage(w, r)
		return
	}
	if r.URL.Path == "/tx" || strings.HasPrefix(r.URL.Path, "/tx/") {
		s.transactionPage(w, r)
		return
	}
	if r.URL.Path == "/find" {
		s.findPage(w, r)
		return
	}
	if r.URL.Path == "/claims" || strings.HasPrefix(r.URL.Path, "/claims/") {
		s.claimsPage(w, r)
		return
	}
	if r.URL.Path == "/realtime" {
		blocks, ok := q("SELECT height,block_time,tx_count FROM block ORDER BY height DESC LIMIT 10")
		if !ok {
			return
		}
		txs, ok := q("SELECT id,value,hash,input_count,output_count,transaction_time,created_at FROM transaction ORDER BY created_at DESC LIMIT 10")
		if !ok {
			return
		}
		s.render(w, r, "realtime", pageData{Title: "Realtime Explorer", Blocks: blocks, Txs: txs})
		return
	}
	if r.URL.Path == "/blocks" || strings.HasPrefix(r.URL.Path, "/blocks/") {
		s.blocksPage(w, r)
		return
	}
	blocks, ok := q("SELECT chainwork,confirmations,difficulty,hash,height,block_time,block_size,tx_count FROM block ORDER BY height DESC LIMIT 6")
	if !ok {
		return
	}
	claims, ok := q("SELECT Claims.*,C.name AS publisher FROM claim Claims LEFT JOIN claim C ON C.claim_id = Claims.publisher_id ORDER BY Claims.created_at DESC LIMIT 5")
	if !ok {
		return
	}
	highest := Row{"height": nil, "difficulty": nil}
	if len(blocks) > 0 {
		highest = blocks[0]
	}
	s.render(w, r, "home", pageData{Title: "Home", RecentBlocks: blocks, RecentClaims: claims, Highest: highest})
}
