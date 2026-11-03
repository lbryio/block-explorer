package explorer

import (
	"fmt"
	"github.com/makiuchi-d/gozxing"
	"github.com/makiuchi-d/gozxing/qrcode/decoder"
	"github.com/makiuchi-d/gozxing/qrcode/encoder"
	"image"
	"image/color"
	"image/png"
	"net/http"
	"strings"
)

func qrImage(data string) (image.Image, error) {
	first, e := encoder.Encoder_encode(data, decoder.ErrorCorrectionLevel_H, map[gozxing.EncodeHintType]interface{}{gozxing.EncodeHintType_CHARACTER_SET: "UTF-8", gozxing.EncodeHintType_QR_MASK_PATTERN: 0})
	if e != nil {
		return nil, e
	}
	version, versionErr := baconVersion(data, first.GetMode())
	if versionErr != nil {
		return nil, versionErr
	}
	var matrix *encoder.ByteMatrix
	best := int(^uint(0) >> 1)
	// Bacon 3.1 requires four white modules within the matrix for penalty
	// rule 3; ZXing treats the outside border as white. Score all eight
	// encodings with Bacon's rule to preserve Endroid's exact mask choice.
	for mask := 0; mask < 8; mask++ {
		code, e := encoder.Encoder_encode(data, decoder.ErrorCorrectionLevel_H, map[gozxing.EncodeHintType]interface{}{gozxing.EncodeHintType_CHARACTER_SET: "UTF-8", gozxing.EncodeHintType_QR_MASK_PATTERN: mask, gozxing.EncodeHintType_QR_VERSION: version})
		if e != nil {
			return nil, e
		}
		candidate := code.GetMatrix()
		score := baconPenalty(candidate)
		if score < best {
			best = score
			matrix = candidate
		}
	}
	n := matrix.GetWidth()
	block := 300 / n
	margin := (320 - n*block) / 2
	img := image.NewGray(image.Rect(0, 0, 320, 320))
	for i := range img.Pix {
		img.Pix[i] = 255
	}
	for y := 0; y < n; y++ {
		for x := 0; x < n; x++ {
			if matrix.Get(x, y) != 1 {
				continue
			}
			for dy := 0; dy < block; dy++ {
				for dx := 0; dx < block; dx++ {
					img.SetGray(margin+x*block+dx, margin+y*block+dy, color.Gray{0})
				}
			}
		}
	}
	return img, nil
}
func (s *Server) qr(w http.ResponseWriter, r *http.Request) {
	data := firstArgument(r, "/qr")
	trimmed := strings.Trim(data, " \t\n\r\x00\x0b")
	if data == "0" || len(trimmed) == 0 || len(trimmed) > 50 {
		jsonError(w, 400, "Invalid QR code data.")
		return
	}
	img, e := qrImage(data)
	if e != nil {
		internalError(w)
		return
	}
	w.Header().Set("Content-Type", "image/png")
	png.Encode(w, img)
}

func baconPenalty(m *encoder.ByteMatrix) int {
	score := encoder.MaskUtil_applyMaskPenaltyRule1(m) + encoder.MaskUtil_applyMaskPenaltyRule2(m) + encoder.MaskUtil_applyMaskPenaltyRule4(m)
	n := m.GetWidth()
	for axis := 0; axis < 2; axis++ {
		for line := 0; line < n; line++ {
			at := func(i int) int8 {
				if axis == 0 {
					return m.Get(i, line)
				}
				return m.Get(line, i)
			}
			white := func(a, b int) bool {
				if a < 0 || b > n {
					return false
				}
				for i := a; i < b; i++ {
					if at(i) != 0 {
						return false
					}
				}
				return true
			}
			for i := 0; i+6 < n; i++ {
				if at(i) == 1 && at(i+1) == 0 && at(i+2) == 1 && at(i+3) == 1 && at(i+4) == 1 && at(i+5) == 0 && at(i+6) == 1 && (white(i-4, i) || white(i+7, i+11)) {
					score += 40
				}
			}
		}
	}
	return score
}

func baconVersion(data string, mode *decoder.Mode) (int, error) {
	header, bits := 4, 0
	switch mode {
	case decoder.Mode_BYTE:
		header += 12
		bits = len(data) * 8
	case decoder.Mode_NUMERIC:
		bits = len(data) / 3 * 10
		if len(data)%3 == 1 {
			bits += 4
		} else if len(data)%3 == 2 {
			bits += 7
		}
	case decoder.Mode_ALPHANUMERIC:
		bits = len(data)/2*11 + len(data)%2*6
	default:
		return 0, fmt.Errorf("unsupported QR encoding mode")
	}
	choose := func(countBits int) (*decoder.Version, error) {
		for n := 1; n <= 40; n++ {
			v, _ := decoder.Version_GetVersionForNumber(n)
			capacity := v.GetTotalCodewords() - v.GetECBlocksForLevel(decoder.ErrorCorrectionLevel_H).GetTotalECCodewords()
			if capacity >= (header+countBits+bits+8)/8 {
				return v, nil
			}
		}
		return nil, fmt.Errorf("QR data too big")
	}
	v1, _ := decoder.Version_GetVersionForNumber(1)
	provisional, e := choose(mode.GetCharacterCountBits(v1))
	if e != nil {
		return 0, e
	}
	version, e := choose(mode.GetCharacterCountBits(provisional))
	if e != nil {
		return 0, e
	}
	return version.GetVersionNumber(), nil
}
