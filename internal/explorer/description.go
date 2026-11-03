package explorer

import (
	"html"
	"html/template"
	"regexp"
	"strings"
)

var descriptionURL = regexp.MustCompile(`(?i)((https?|ftp|lbry)://([A-Za-z0-9\-/]+|\S*?\.\S*?))([\s)\[\]{},;"':<]|\.\s|$)`)
var descriptionEmail = regexp.MustCompile(`[a-zA-Z0-9.!#$%&'*+/=?^_` + "`" + `{|}~-]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$`)

func description(v any) template.HTML {
	s := text(v)
	if phpTrim(s) == "" {
		return template.HTML("<em>No description available.</em>")
	}
	var b strings.Builder
	end := 0
	for _, m := range descriptionURL.FindAllStringSubmatchIndex(s, -1) {
		b.WriteString(html.EscapeString(s[end:m[2]]))
		url := s[m[2]:m[3]]
		escaped := html.EscapeString(url)
		b.WriteString(`<a href="` + escaped + `" target="_blank" rel="nofollow">` + escaped + `</a>`)
		b.WriteString(html.EscapeString(s[m[8]:m[9]]))
		end = m[1]
	}
	tail := s[end:]
	if loc := descriptionEmail.FindStringIndex(tail); loc != nil {
		b.WriteString(html.EscapeString(tail[:loc[0]]))
		mail := html.EscapeString(tail[loc[0]:loc[1]])
		b.WriteString(`<a href="mailto:` + mail + `" rel="nofollow">` + mail + `</a>`)
	} else {
		b.WriteString(html.EscapeString(tail))
	}
	return template.HTML(strings.ReplaceAll(b.String(), "\n", "<br />"))
}
