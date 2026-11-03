package explorer

import (
	"bytes"
	"crypto/sha256"
	"encoding/json"
	"fmt"
	"github.com/makiuchi-d/gozxing"
	"github.com/makiuchi-d/gozxing/qrcode"
	"image/png"
	"net/http/httptest"
	"net/url"
	"os"
	"strings"
	"testing"
)

func TestQRRendering(t *testing.T) {
	raw, err := os.ReadFile("testdata/qr.json")
	if err != nil {
		t.Fatal(err)
	}
	var cases []struct {
		Data          string
		Width, Height int
		SHA256        string
	}
	if err = json.Unmarshal(raw, &cases); err != nil {
		t.Fatal(err)
	}
	if len(cases) < 50 {
		t.Fatal("missing QR regression cases")
	}
	for _, c := range cases {
		t.Run(c.Data, func(t *testing.T) {
			img, err := qrImage(c.Data)
			if err != nil {
				t.Fatal(err)
			}
			if img.Bounds().Dx() != c.Width || img.Bounds().Dy() != c.Height {
				t.Fatal("QR dimensions changed", img.Bounds())
			}
			pixels := make([]byte, 0, c.Width*c.Height*4)
			for y := img.Bounds().Min.Y; y < img.Bounds().Max.Y; y++ {
				for x := img.Bounds().Min.X; x < img.Bounds().Max.X; x++ {
					r, g, b, a := img.At(x, y).RGBA()
					pixels = append(pixels, byte(r>>8), byte(g>>8), byte(b>>8), byte(a>>8))
				}
			}
			if got := fmt.Sprintf("%x", sha256.Sum256(pixels)); got != c.SHA256 {
				t.Fatal("QR pixels changed", got, c.SHA256)
			}
		})
	}
}

func TestQRPayloadAndRouting(t *testing.T) {
	for _, data := range []string{"lbry://hello/path", " hello ", "éà😀", strings.Repeat("a", 50)} {
		w := httptest.NewRecorder()
		s := Server{}
		s.ServeHTTP(w, httptest.NewRequest("GET", "/qr/"+url.PathEscape(data), nil))
		if w.Code != 200 || w.Header().Get("Content-Type") != "image/png" {
			t.Fatal(w.Code, w.Body.String())
		}
		img, e := png.Decode(bytes.NewReader(w.Body.Bytes()))
		if e != nil {
			t.Fatal(e)
		}
		bitmap, e := gozxing.NewBinaryBitmapFromImage(img)
		if e != nil {
			t.Fatal(e)
		}
		result, e := qrcode.NewQRCodeReader().Decode(bitmap, nil)
		if e != nil || result.GetText() != data {
			t.Fatal(data, result, e)
		}
	}
}
func TestQRInvalidBoundaries(t *testing.T) {
	for _, data := range []string{"", "0", " \t\n", strings.Repeat("é", 26), strings.Repeat("a", 51)} {
		w := httptest.NewRecorder()
		s := Server{}
		s.ServeHTTP(w, httptest.NewRequest("GET", "/qr/"+url.PathEscape(data), nil))
		if w.Code != 400 || w.Body.String() != `{"error":true,"message":"Invalid QR code data."}` {
			t.Fatal(data, w.Code, w.Body.String())
		}
	}
}
