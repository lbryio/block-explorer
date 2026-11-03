package explorer

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"strconv"
	"strings"
	"time"

	"github.com/go-sql-driver/mysql"
)

// Store deliberately exposes only SELECT operations. Every query runs in a
// server-enforced read-only transaction, including queries on pooled connections.
type Store interface {
	Query(context.Context, string, ...any) ([]Row, error)
}
type Row map[string]any
type MySQL struct{ DB *sql.DB }

func Open(dsn string) (*MySQL, error) {
	c, err := mysql.ParseDSN(dsn)
	if err != nil {
		return nil, err
	}
	c.ParseTime = false
	c.MultiStatements = false
	c.Timeout = 5 * time.Second
	if c.Params == nil {
		c.Params = map[string]string{}
	}
	c.Params["time_zone"] = "'+00:00'"
	db, err := sql.Open("mysql", c.FormatDSN())
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(4)
	db.SetMaxIdleConns(2)
	db.SetConnMaxLifetime(5 * time.Minute)
	return &MySQL{db}, nil
}
func (s *MySQL) Query(ctx context.Context, q string, args ...any) ([]Row, error) {
	if !strings.HasPrefix(strings.ToUpper(phpTrim(q)), "SELECT ") {
		return nil, errors.New("only SELECT is permitted")
	}
	// Preserve the source query duration, including all=true address history.
	// Caller deadlines and disconnect cancellation still propagate to MySQL.
	tx, err := s.DB.BeginTx(ctx, &sql.TxOptions{ReadOnly: true})
	if err != nil {
		return nil, err
	}
	defer tx.Rollback()
	rows, err := tx.QueryContext(ctx, q, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	cols, err := rows.ColumnTypes()
	if err != nil {
		return nil, err
	}
	out := make([]Row, 0)
	for rows.Next() {
		values := make([]any, len(cols))
		ptr := make([]any, len(cols))
		for i := range values {
			ptr[i] = &values[i]
		}
		if err = rows.Scan(ptr...); err != nil {
			return nil, err
		}
		r := Row{}
		columns := make([]string, len(cols))
		for i, c := range cols {
			columns[i] = c.Name()
		}
		r["__columns"] = columns
		for i, c := range cols {
			v := values[i]
			if b, ok := v.([]byte); ok {
				v = string(b)
			}
			if str, ok := v.(string); ok {
				switch c.DatabaseTypeName() {
				case "TINYINT", "SMALLINT", "MEDIUMINT", "INT", "BIGINT":
					v, err = strconv.ParseInt(str, 10, 64)
				case "FLOAT", "DOUBLE":
					v, err = strconv.ParseFloat(str, 64)
				}
				if err != nil {
					return nil, err
				}
			}
			r[c.Name()] = v
		}
		out = append(out, r)
	}
	return out, rows.Err()
}
func number(v any) float64 {
	switch n := v.(type) {
	case json.Number:
		f, _ := n.Float64()
		return f
	case uint64:
		return float64(n)
	case int32:
		return float64(n)
	case uint32:
		return float64(n)
	case float64:
		return n
	case int64:
		return float64(n)
	case int:
		return float64(n)
	case string:
		f, _ := strconv.ParseFloat(n, 64)
		return f
	}
	return 0
}
