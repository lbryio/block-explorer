package explorer

import (
	"encoding/json"
	"fmt"
	"math"
	"math/big"
	"net/http"
	"strings"
)

type ioView struct {
	Row, Address   Row
	Source, Anchor bool
}

func ioViews(rows []Row, addressKey, source string, seen map[string]bool) []ioView {
	out := make([]ioView, 0, len(rows))
	for _, row := range rows {
		address := Row{}
		if list, ok := row[addressKey].([]Row); ok && len(list) > 0 {
			address = list[0]
		}
		name := text(address["address"])
		anchor := false
		if !seen[name] && phpTrim(text(row["value"])) != "" && !truth(row["is_coinbase"]) {
			anchor = true
			seen[name] = true
		}
		out = append(out, ioView{row, address, name != "" && name == source, anchor})
	}
	return out
}
func confirmationDelay(tx Row) string {
	diff := int64(number(tx["transaction_time"])) - timestamp(tx["created_at"]).Unix()
	if diff <= 0 {
		return ""
	}
	n := int(diff)
	unit := "second"
	if diff > 60 {
		n = int(math.Ceil(float64(diff) / 60))
		unit = "minute"
	}
	return fmt.Sprintf(" (+%s)", unitString(n, unit))
}
func (s *Server) transactionPage(w http.ResponseWriter, r *http.Request) {
	hash := firstArgument(r, "/tx")
	if hash == "" {
		redirect(w, r, "/", 302)
		return
	}
	query := func(q string, args ...any) ([]Row, bool) {
		rows, e := s.Store.Query(r.Context(), q, args...)
		if e != nil {
			internalError(w)
			return nil, false
		}
		return rows, true
	}
	rows, ok := query("SELECT * FROM transaction WHERE hash = ? LIMIT 1", hash)
	if !ok {
		return
	}
	if len(rows) == 0 {
		redirect(w, r, "/", 302)
		return
	}
	tx := rows[0]
	var block Row
	confirmations := int64(0)
	if tx["block_hash_id"] != nil && text(tx["block_hash_id"]) != "MEMPOOL" {
		blocks, ok := query("SELECT confirmations,height FROM block WHERE hash = ? LIMIT 1", tx["block_hash_id"])
		if !ok {
			return
		}
		last, ok := query("SELECT height FROM block ORDER BY height DESC LIMIT 1")
		if !ok {
			return
		}
		if len(blocks) > 0 {
			block = blocks[0]
			if len(last) > 0 {
				confirmations = int64(number(last[0]["height"]) - number(block["height"]) + 1)
			}
		}
	}
	inputs, ok := query("SELECT * FROM input WHERE transaction_id = ? ORDER BY prevout_n ASC", tx["id"])
	if !ok {
		return
	}
	for _, in := range inputs {
		in["input_addresses"] = []Row{}
		if in["input_address_id"] != nil {
			addresses, ok := query("SELECT id,address FROM address WHERE id = ?", in["input_address_id"])
			if !ok {
				return
			}
			in["input_addresses"] = addresses
		}
	}
	outputs, ok := query("SELECT Outputs.*,I.transaction_hash AS spend_input_hash,I.id AS spend_input_id FROM output Outputs LEFT JOIN input I ON I.id = Outputs.spent_by_input_id WHERE Outputs.transaction_id = ? ORDER BY Outputs.vout ASC", tx["id"])
	if !ok {
		return
	}
	for _, out := range outputs {
		script := text(out["script_pub_key_asm"])
		out["IsClaim"] = strings.Contains(script, "CLAIM")
		out["IsSupportClaim"] = strings.Contains(script, "SUPPORT_CLAIM")
		out["IsUpdateClaim"] = strings.Contains(script, "UPDATE_CLAIM")
		claims, ok := query("SELECT id,claim_id,claim_address,vout,transaction_hash_id FROM claim WHERE transaction_hash_id = ? AND vout = ? LIMIT 1", tx["hash"], out["vout"])
		if !ok {
			return
		}
		var claim Row
		if len(claims) > 0 {
			claim = claims[0]
		}
		out["Claim"] = claim
		var list []any
		json.Unmarshal([]byte(text(out["address_list"])), &list)
		if len(list) == 0 && text(claim["claim_address"]) != "" {
			list = append(list, claim["claim_address"])
		}
		out["output_addresses"] = []Row{}
		if len(list) > 0 {
			addresses, ok := query("SELECT address FROM address WHERE address IN ("+placeholders(len(list))+")", list...)
			if !ok {
				return
			}
			out["output_addresses"] = addresses
		}
	}
	totalIn, totalOut := new(big.Rat), new(big.Rat)
	for _, in := range inputs {
		if !validBCString(phpString(in["value"])) {
			internalError(w)
			return
		}
		totalIn = decimal(truncated(new(big.Rat).Add(totalIn, decimal(phpString(in["value"]))), 8))
	}
	for _, out := range outputs {
		if !validBCString(phpString(out["value"])) {
			internalError(w)
			return
		}
		totalOut = decimal(truncated(new(big.Rat).Add(totalOut, decimal(phpString(out["value"]))), 8))
	}
	fee := truncated(new(big.Rat).Sub(totalIn, totalOut), 8)
	seen := map[string]bool{}
	source := queryScalar(r, "address")
	data := pageData{Title: "Transaction ", Tx: tx, Block: block, Confirmations: confirmations, Fee: fee}
	data.Inputs = ioViews(inputs, "input_addresses", source, seen)
	data.Outputs = ioViews(outputs, "output_addresses", source, seen)
	s.render(w, r, "tx", data)
}
