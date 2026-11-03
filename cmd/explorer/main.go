package main

import (
	"context"
	"explorer/internal/explorer"
	"log"
	"log/slog"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"
)

func main() {
	dsn := os.Getenv("EXPLORER_MYSQL_DSN")
	if dsn == "" {
		log.Fatal("EXPLORER_MYSQL_DSN is required")
	}
	store, e := explorer.Open(dsn)
	if e != nil {
		log.Fatal("invalid MySQL configuration")
	}
	defer store.DB.Close()
	addr := os.Getenv("EXPLORER_LISTEN")
	if addr == "" {
		addr = "127.0.0.1:8080"
	}
	cache := os.Getenv("EXPLORER_CACHE_DIR")
	if cache == "" {
		cache = "var/cache/go"
	}
	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))
	observed := explorer.ObservedStore{Store: store, Logger: logger, Stats: store.DB.Stats}
	app := &explorer.Server{Store: observed, Logger: logger, Cache: explorer.Cache{Dir: cache}}
	server := &http.Server{Addr: addr, Handler: explorer.AccessLog(app, logger), ReadHeaderTimeout: 5 * time.Second, IdleTimeout: 60 * time.Second}
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()
	listener, e := net.Listen("tcp", addr)
	if e != nil {
		log.Fatal(e)
	}
	log.Printf("listening on %s", listener.Addr())
	if e = runServer(ctx, server, listener); e != nil {
		log.Fatal(e)
	}
}

// Wait for Shutdown to drain active requests before returning from main.
func runServer(ctx context.Context, server *http.Server, listener net.Listener) error {
	ctx, cancel := context.WithCancel(ctx)
	defer cancel()
	defer listener.Close()
	stopped := make(chan error, 1)
	go func() {
		<-ctx.Done()
		shutdownCtx, stop := context.WithTimeout(context.Background(), 10*time.Second)
		defer stop()
		err := server.Shutdown(shutdownCtx)
		if err != nil {
			server.Close()
		}
		stopped <- err
	}()
	if err := server.Serve(listener); err != nil && err != http.ErrServerClosed {
		return err
	}
	return <-stopped
}
