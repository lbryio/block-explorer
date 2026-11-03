package explorer

import (
	"encoding/json"
	"os"
	"testing"
	"time"
)

func TestFormatting(t *testing.T) {
	b, e := os.ReadFile("testdata/formatting.json")
	if e != nil {
		t.Fatal(e)
	}
	var fixtures struct {
		Now   int64
		Dates []struct {
			Timestamp  int64
			Ago, Human string
		}
		Amounts []struct {
			Value                   any
			Amount, Plain, Currency string
		}
	}
	if e = json.Unmarshal(b, &fixtures); e != nil {
		t.Fatal(e)
	}
	now := time.Unix(fixtures.Now, 0).UTC()
	for _, f := range fixtures.Dates {
		tm := time.Unix(f.Timestamp, 0).UTC()
		if got := cakeAgo(tm, now); got != f.Ago {
			t.Errorf("ago %d: %q != expected %q", f.Timestamp, got, f.Ago)
		}
		if got := humanAgo(tm, now); got != f.Human {
			t.Errorf("human %d: %q != expected %q", f.Timestamp, got, f.Human)
		}
	}
	for _, f := range fixtures.Amounts {
		if got := amount(f.Value, ","); got != f.Amount {
			t.Errorf("amount %v: %q != expected %q", f.Value, got, f.Amount)
		}
		if got := amount(f.Value, ""); got != f.Plain {
			t.Errorf("plain %v: %q != expected %q", f.Value, got, f.Plain)
		}
		if got := currency(f.Value); got != f.Currency {
			t.Errorf("currency %v: %q != expected %q", f.Value, got, f.Currency)
		}
	}
}
