package explorer

import (
	"net/http"
	"regexp"
	"strconv"
	"strings"
)

type claimCard struct {
	Claim             Row
	LastItem, LastRow bool
}

func cards(rows []Row) []claimCard {
	out := make([]claimCard, 0, len(rows))
	lastRow := (len(rows) + 2) / 3
	for i, row := range rows {
		out = append(out, claimCard{row, (i+1)%3 == 0, i/3+1 == lastRow})
	}
	return out
}
func claimCost(r Row) string {
	if number(r["price"]) > 0 {
		return currency(r["price"]) + " LBC"
	}
	if r["fee"] != nil && strings.ToLower(text(r["fee_currency"])) == "lbc" {
		return currency(r["fee"]) + " LBC"
	}
	return ""
}
func (s *Server) claimsPage(w http.ResponseWriter, r *http.Request) {
	rest := strings.TrimPrefix(strings.TrimPrefix(r.URL.Path, "/claims"), "/")
	if rest != "" && rest != "0" {
		s.claimPage(w, r, firstArgument(r, "/claims"))
		return
	}
	rows, e := s.Store.Query(r.Context(), "SELECT MAX(id) AS MaxId FROM claim")
	if e != nil {
		internalError(w)
		return
	}
	maxID := 0
	if len(rows) > 0 {
		maxID = int(number(rows[0]["MaxId"]))
	}
	page, pages, pagination := paginate(20000000, 96, queryInt(r, "page"))
	start := maxID - page*96
	end := min(maxID, start+96)
	list := s.blocked(r.Context())
	claims, e := s.Store.Query(r.Context(), "SELECT Claims.*,C.name AS publisher FROM claim Claims LEFT JOIN claim C ON C.claim_id = Claims.publisher_id WHERE Claims.id > ? AND Claims.id <= ? ORDER BY Claims.id DESC", start, end)
	if e != nil {
		internalError(w)
		return
	}
	markBlocked(claims, list)
	s.render(w, r, "claims", pageData{Title: "Claims Explorer", Claims: claims, Cards: cards(claims), NumRecords: 20000000, PageLimit: 96, CurrentPage: page, NumPages: pages, Pagination: pagination})
}

var numericPattern = regexp.MustCompile(`^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$`)

func (s *Server) findPage(w http.ResponseWriter, r *http.Request) {
	criteria := phpTrim(queryString(r, "q"))
	var claims []Row
	query := func(q string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), q, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	redirect := func(path string) { redirect(w, r, path, 302) }
	switch {
	case numericPattern.MatchString(criteria):
		n, _ := strconv.ParseFloat(criteria, 64)
		height := int(n)
		rows, ok := query("SELECT id FROM block WHERE height = ? LIMIT 1", height)
		if !ok {
			return
		}
		if len(rows) > 0 {
			redirect("/blocks/" + strconv.Itoa(height))
			return
		}
	case len(criteria) == 34:
		rows, ok := query("SELECT id,address FROM address WHERE address = ? LIMIT 1", criteria)
		if !ok {
			return
		}
		if len(rows) > 0 {
			redirect("/address/" + text(rows[0]["address"]))
			return
		}
	case len(criteria) == 40:
		rows, ok := query("SELECT claim_id FROM claim WHERE claim_id = ? LIMIT 1", criteria)
		if !ok {
			return
		}
		if len(rows) > 0 {
			redirect("/claims/" + text(rows[0]["claim_id"]))
			return
		}
	case len(criteria) == 64:
		rows, ok := query("SELECT height FROM block WHERE hash = ? LIMIT 1", criteria)
		if !ok {
			return
		}
		if len(rows) > 0 {
			redirect("/blocks/" + text(rows[0]["height"]))
			return
		}
		rows, ok = query("SELECT hash FROM transaction WHERE hash = ? LIMIT 1", criteria)
		if !ok {
			return
		}
		if len(rows) > 0 {
			redirect("/tx/" + text(rows[0]["hash"]))
			return
		}
	default:
		var ok bool
		claims, ok = query("SELECT Claims.* FROM claim Claims WHERE name = ? GROUP BY claim_id ORDER BY FIELD(bid_state, 'Controlling') DESC LIMIT 10", criteria)
		if !ok {
			return
		}
		if len(claims) == 1 {
			redirect("/claims/" + text(claims[0]["claim_id"]))
			return
		}
	}
	s.render(w, r, "find", pageData{Title: "Search Results", Claims: claims, Cards: cards(claims)})
}

func (s *Server) claimPage(w http.ResponseWriter, r *http.Request, id string) {
	query := func(q string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), q, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	rows, ok := query("SELECT Claims.*,C.name AS publisher FROM claim Claims LEFT JOIN claim C ON C.claim_id = Claims.publisher_id WHERE Claims.claim_id = ? ORDER BY Claims.created_at DESC LIMIT 1", id)
	if !ok {
		return
	}
	if len(rows) == 0 {
		redirect(w, r, "/", 302)
		return
	}
	claim := rows[0]
	var more []Row
	if claim["publisher"] != nil && text(claim["publisher_id"]) != "f2cf43b86b9d70175dc22dbb9ff7806241d90780" {
		more, ok = query("SELECT Claims.claim_id,Claims.bid_state,Claims.fee,Claims.fee_currency,Claims.is_nsfw,Claims.claim_type,Claims.name,Claims.title,Claims.description,Claims.content_type,Claims.language,Claims.author,Claims.license,Claims.created_at,C.name AS publisher FROM claim Claims LEFT JOIN claim C ON C.claim_id = Claims.publisher_id WHERE Claims.claim_type = 1 AND Claims.id <> ? AND Claims.publisher_id = ? ORDER BY Claims.fee DESC,RAND() DESC LIMIT 9", claim["id"], claim["publisher_id"])
		if !ok {
			return
		}
	}
	blocked := s.blocked(r.Context())
	markBlocked(rows, blocked)
	isBlocked := truth(claim["isBlocked"])
	if isBlocked {
		more = nil
	}
	s.render(w, r, "claim", pageData{Title: "Claim • " + text(claim["name"]), Claim: claim, MoreClaims: more, Cards: cards(more), ClaimIsBlocked: isBlocked})
}
