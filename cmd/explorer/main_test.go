package main

import (
	"context"
	"io"
	"net"
	"net/http"
	"testing"
	"time"
)

func TestShutdownDrainsActiveRequest(t *testing.T) {
	listener, e := net.Listen("tcp", "127.0.0.1:0")
	if e != nil {
		t.Fatal(e)
	}
	entered, release := make(chan struct{}), make(chan struct{})
	server := &http.Server{Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { close(entered); <-release; io.WriteString(w, "complete") })}
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stopped := make(chan error, 1)
	go func() { stopped <- runServer(ctx, server, listener) }()
	result := make(chan string, 1)
	go func() {
		resp, e := http.Get("http://" + listener.Addr().String())
		if e != nil {
			result <- e.Error()
			return
		}
		defer resp.Body.Close()
		body, _ := io.ReadAll(resp.Body)
		result <- string(body)
	}()
	select {
	case <-entered:
	case <-time.After(time.Second):
		t.Fatal("request did not start")
	}
	cancel()
	select {
	case e := <-stopped:
		t.Fatal("shutdown returned before request drained", e)
	case <-time.After(30 * time.Millisecond):
	}
	close(release)
	select {
	case body := <-result:
		if body != "complete" {
			t.Fatal(body)
		}
	case <-time.After(time.Second):
		t.Fatal("request did not finish")
	}
	select {
	case e := <-stopped:
		if e != nil {
			t.Fatal(e)
		}
	case <-time.After(time.Second):
		t.Fatal("shutdown did not finish")
	}
}
