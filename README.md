> This is a Go rewrite of the LBRY block explorer. The previous PHP implementation is preserved on the [`php-version`](https://github.com/lbryio/block-explorer/tree/php-version) branch of this repository.

# LBRY explorer

A read-only block explorer for the LBRY blockchain.

## Build and run

Requires Go 1.23 or newer. From this directory:

```sh
make verify
make release
```

Release executables are written to `dist/release/`:

| Executable | Platform |
| --- | --- |
| `explorer-mac-arm64` | macOS, Apple Silicon |
| `explorer-linux-amd64` | Linux, x86-64 |

Set `EXPLORER_MYSQL_DSN` using a private environment file or your service manager,
then run the executable for your platform. For example, on an Apple Silicon Mac:

```sh
./dist/release/explorer-mac-arm64
```

Open <http://127.0.0.1:8080>. For development, use `go run ./cmd/explorer`.
The application reads its configuration from the process environment; it does
not automatically load `.env` files.

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `EXPLORER_MYSQL_DSN` | required | MySQL driver DSN, such as `USER:PASSWORD@tcp(HOST:3306)/chainquery` |
| `EXPLORER_LISTEN` | `127.0.0.1:8080` | HTTP listener |
| `EXPLORER_CACHE_DIR` | `var/cache/go` | Writable disk-cache directory, relative to the working directory |

Use a SELECT-only database account. Queries run in read-only transactions with
UTC connections, a five-second connection timeout and a pool of at most four
connections. Chainquery manages database population independently.

Cached blocked-content and inflation responses expire after ten minutes; supply
responses expire after one hour. Cache files persist across restarts.

Tests use local HTTP mocks and checked-in regression fixtures; no database access
or external services are required for `make verify`.

## Caddy

Set `EXPLORER_LISTEN=127.0.0.1:8001` to use port 8001. A Caddy configuration for
that listener is:

```caddyfile
explorer.lbry.com {
    reverse_proxy 127.0.0.1:8001
}

explorer.lbry.io {
    redir https://explorer.lbry.com{uri} permanent
}
```

See [TODO.md](TODO.md) for known issues and [THIRD-PARTY.md](THIRD-PARTY.md) for notices.
