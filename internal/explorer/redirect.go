package explorer

import (
	"net/http"
)

type headWriter struct{ http.ResponseWriter }

func (w headWriter) Write(b []byte) (int, error) { return len(b), nil }
func redirect(w http.ResponseWriter, r *http.Request, location string, status int) {
	scheme := "http"
	if r.TLS != nil || r.URL.Scheme == "https" || r.Header.Get("X-Forwarded-Proto") == "https" {
		scheme = "https"
	}
	w.Header().Set("Location", scheme+"://"+r.Host+location)
	w.Header().Set("Content-Type", "text/html; charset=UTF-8")
	w.WriteHeader(status)
}
