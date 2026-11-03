package explorer

import (
	"context"
	"net/http"
	"net/http/httptrace"
	"sync"
	"time"
)

// CURLOPT_CONNECTTIMEOUT includes DNS, TCP, proxy CONNECT and TLS. A dial
// timeout alone leaves a second TLS timeout window, so bound GetConn..GotConn.
// The separate client timeout continues to cover response transfer.
func connectionBudget(req *http.Request, budget time.Duration) (*http.Request, func()) {
	ctx, cancel := context.WithCancel(req.Context())
	var mu sync.Mutex
	var timer *time.Timer
	closed := false
	stopTimer := func() {
		if timer != nil {
			timer.Stop()
			timer = nil
		}
	}
	trace := &httptrace.ClientTrace{GetConn: func(string) {
		mu.Lock()
		defer mu.Unlock()
		if closed {
			return
		}
		stopTimer()
		timer = time.AfterFunc(budget, cancel)
	}, GotConn: func(httptrace.GotConnInfo) { mu.Lock(); defer mu.Unlock(); stopTimer() }}
	finish := func() { mu.Lock(); closed = true; stopTimer(); mu.Unlock(); cancel() }
	return req.WithContext(httptrace.WithClientTrace(ctx, trace)), finish
}
