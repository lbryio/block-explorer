package explorer

import (
	"fmt"
	"html/template"
	"net/http"
	"net/url"
	"strings"
)

func internalError(w http.ResponseWriter) {
	body, _ := templateFiles.ReadFile("templates/error500.html")
	w.Header().Set("Content-Type", "text/html; charset=UTF-8")
	w.WriteHeader(500)
	w.Write(body)
}

func clientError(w http.ResponseWriter, r *http.Request, status int, message string) {
	body, _ := templateFiles.ReadFile("templates/error400.html")
	t := template.Must(template.New("error").Parse(string(body)))
	w.Header().Set("Content-Type", "text/html; charset=UTF-8")
	w.WriteHeader(status)
	target := r.RequestURI
	if strings.HasPrefix(target, "http://") || strings.HasPrefix(target, "https://") {
		if u, e := url.Parse(target); e == nil {
			target = u.RequestURI()
		}
	}
	t.Execute(w, struct{ Message, URL string }{message, target})
}
func badMethodError(w http.ResponseWriter, r *http.Request) {
	clientError(w, r, 400, fmt.Sprintf("Invalid HTTP method received. `%s` is invalid.", strings.ToUpper(r.Method)))
}
