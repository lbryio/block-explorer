package explorer

import (
	"context"
	"net/http"
	"net/url"
	"regexp"
	"strings"
)

type routeMatch struct {
	Action, Canonical string
	Args              []string
}
type routeKey struct{}

var apiAddressPattern = regexp.MustCompile(`^/api/v1/address/([A-Za-z0-9,]+)/utxo/*$`)
var apiBalancePattern = regexp.MustCompile(`^/api/v1/address/([A-Za-z0-9]+)/balance/*$`)
var apiPeriodPattern = regexp.MustCompile(`^/api/v1/charts/blocksize/([012346789dhy]+)/*$`)

func matchRoute(path, method string) routeMatch {
	path = "/" + strings.TrimLeft(path, "/")
	valid := false
	for _, m := range []string{"GET", "PUT", "POST", "PATCH", "DELETE", "OPTIONS", "HEAD"} {
		if strings.ToUpper(method) == m {
			valid = true
		}
	}
	if !valid {
		return routeMatch{Action: "badmethod"}
	}
	if strings.Contains(path, "%") {
		parts := strings.Split(path, "/")
		for i, p := range parts {
			decoded, e := url.QueryUnescape(p)
			if e == nil {
				parts[i] = strings.ReplaceAll(decoded, "/", "%2f")
			}
		}
		path = strings.Join(parts, "/")
	}
	trimmed := strings.TrimRight(path, "/")
	if trimmed == "" {
		trimmed = "/"
	}
	for _, rule := range []struct {
		Pattern                *regexp.Regexp
		Action, Prefix, Suffix string
	}{{apiAddressPattern, "apiaddrutxo", "/api/v1/address/", "/utxo"}, {apiBalancePattern, "apiaddrbalance", "/api/v1/address/", "/balance"}, {apiPeriodPattern, "apiblocksize", "/api/v1/charts/blocksize/", ""}} {
		if m := rule.Pattern.FindStringSubmatch(path); m != nil {
			return routeMatch{rule.Action, rule.Prefix + m[1] + rule.Suffix, []string{m[1]}}
		}
	}
	static := map[string]string{"/api/v1/charts/inflation": "apiinflation", "/api/v1/realtime/blocks": "apirealtimeblocks", "/api/v1/realtime/tx": "apirealtimetx", "/api/v1/recentblocks": "apirecentblocks", "/api/v1/status": "apistatus", "/api/v1/supply": "apiutxosupply", "/api/v1/claims/browse": "apibrowse", "/": "index", "/find": "find", "/realtime": "realtime", "/stats": "stats"}
	if action, ok := static[trimmed]; ok {
		return routeMatch{action, trimmed, []string{}}
	}
	for _, action := range []string{"address", "blocks", "claims", "tx", "qr"} {
		prefix := "/" + action
		if path == prefix || strings.HasPrefix(path, prefix+"/") {
			args := []string{}
			for _, arg := range strings.Split(strings.TrimPrefix(path, prefix), "/") {
				if arg != "" {
					v, e := url.PathUnescape(arg)
					if e == nil {
						args = append(args, v)
					}
				}
			}
			canonical := prefix
			if len(args) > 0 {
				canonical += "/" + args[0]
			}
			return routeMatch{action, canonical, args}
		}
	}
	return routeMatch{"index", "/", []string{}}
}
func routedRequest(r *http.Request, match routeMatch) *http.Request {
	copy := r.Clone(context.WithValue(r.Context(), routeKey{}, match))
	u := *r.URL
	u.Path = match.Canonical
	u.RawPath = ""
	copy.URL = &u
	return copy
}
func firstArgument(r *http.Request, prefix string) string {
	if match, ok := r.Context().Value(routeKey{}).(routeMatch); ok {
		if len(match.Args) > 0 {
			return match.Args[0]
		}
		return ""
	}
	path := strings.TrimPrefix(r.URL.EscapedPath(), prefix)
	path = strings.TrimPrefix(path, "/")
	for _, part := range strings.Split(path, "/") {
		if part != "" {
			decoded, e := url.PathUnescape(part)
			if e == nil {
				return decoded
			}
		}
	}
	return ""
}
