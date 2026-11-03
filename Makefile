.PHONY: test verify release

test:
	go test ./...
	go vet ./...

verify: test
	go test -race ./...

release:
	mkdir -p dist/release
	set -e; for target in darwin/arm64 linux/amd64; do \
		os=$${target%/*}; arch=$${target#*/}; \
		name=$$os; if [ "$$os" = darwin ]; then name=mac; fi; \
		CGO_ENABLED=0 GOOS=$$os GOARCH=$$arch go build -trimpath \
			-o dist/release/explorer-$$name-$$arch ./cmd/explorer; \
	done
	cp LICENSE THIRD-PARTY.md dist/release/
	cp -a licenses dist/release/
