package explorer

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
	"time"
)

type Cache struct {
	Dir string
	Now func() time.Time
}
type cacheEntry struct {
	Expires time.Time       `json:"expires"`
	Body    json.RawMessage `json:"body"`
}

func (c Cache) now() time.Time {
	if c.Now != nil {
		return c.Now()
	}
	return time.Now()
}
func (c Cache) Get(key string) ([]byte, bool) {
	b, e := os.ReadFile(filepath.Join(c.Dir, key+".json"))
	if e != nil {
		return nil, false
	}
	var v cacheEntry
	if json.Unmarshal(b, &v) != nil || !c.now().Before(v.Expires) {
		return nil, false
	}
	return v.Body, true
}
func (c Cache) Put(key string, b []byte, ttl time.Duration) error {
	if e := os.MkdirAll(c.Dir, 0700); e != nil {
		return e
	}
	v, e := json.Marshal(cacheEntry{c.now().Add(ttl), b})
	if e != nil {
		return e
	}
	f, e := os.CreateTemp(c.Dir, ".cache-")
	if e != nil {
		return e
	}
	name := f.Name()
	defer os.Remove(name)
	if _, e = f.Write(v); e != nil {
		f.Close()
		return e
	}
	if e = f.Close(); e != nil {
		return e
	}
	return os.Rename(name, filepath.Join(c.Dir, key+".json"))
}

// Cake accepts only PHP arrays for these API cache entries. JSON objects and
// arrays are their Go-cache representations; primitives/null remain misses.
func (s *Server) cachedArray(key string) ([]byte, bool) {
	b, ok := s.Cache.Get(key)
	if !ok {
		return nil, false
	}
	trimmed := bytes.TrimSpace(b)
	if len(trimmed) == 0 || (trimmed[0] != '{' && trimmed[0] != '[') {
		return nil, false
	}
	return b, true
}
