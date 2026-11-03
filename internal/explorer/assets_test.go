package explorer

import (
	"bytes"
	"explorer/webroot"
	"io/fs"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
)

func TestBundledAssetsEmbedded(t *testing.T) {
	reference := filepath.Join("..", "..", "webroot")
	count := 0
	e := filepath.WalkDir(reference, func(path string, d fs.DirEntry, e error) error {
		if e != nil {
			return e
		}
		if d.IsDir() {
			return nil
		}
		name, _ := filepath.Rel(reference, path)
		if name == "embed.go" {
			return nil
		}
		want, e := os.ReadFile(path)
		if e != nil {
			return e
		}
		embedded, e := webroot.Files.ReadFile(filepath.ToSlash(name))
		if e != nil {
			return e
		}
		if !bytes.Equal(want, embedded) {
			t.Errorf("embedded asset bytes changed: %s", name)
		}
		count++
		return nil
	})
	if e != nil {
		t.Fatal(e)
	}
	if count < 100 {
		t.Fatal("incomplete asset inventory", count)
	}
}

func TestEmbeddedAssetHTTP(t *testing.T) {
	s := Server{}
	for _, name := range []string{"css/main.css", "js/jquery.js", "img/lbry.png", "amcharts/serial.js", "amcharts/plugins/export/export.min.js"} {
		t.Run(name, func(t *testing.T) {
			want, e := webroot.Files.ReadFile(name)
			if e != nil {
				t.Fatal(e)
			}
			for _, method := range []string{"GET", "HEAD"} {
				w := httptest.NewRecorder()
				s.ServeHTTP(w, httptest.NewRequest(method, "/"+name, nil))
				if w.Code != 200 || w.Header().Get("Content-Type") == "" {
					t.Fatalf("%s: status %d, headers %v", method, w.Code, w.Header())
				}
				if method == "GET" && !bytes.Equal(w.Body.Bytes(), want) {
					t.Fatal("asset body differs")
				}
				if method == "HEAD" && w.Body.Len() != 0 {
					t.Fatal("HEAD returned a body")
				}
			}
			r := httptest.NewRequest("GET", "/"+name, nil)
			r.Header.Set("Range", "bytes=0-9")
			w := httptest.NewRecorder()
			s.ServeHTTP(w, r)
			if w.Code != 206 || !bytes.Equal(w.Body.Bytes(), want[:10]) {
				t.Fatal("range response differs", w.Code)
			}
		})
	}
}
