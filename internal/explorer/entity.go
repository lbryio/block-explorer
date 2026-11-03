package explorer

// Cake's schema-driven entity conversions apply to browse results, unlike
// raw PDO rows used by UTXO and chart queries.
func claimEntities(rows []Row) []Row {
	if rows == nil {
		return []Row{}
	}
	for _, r := range rows {
		for _, field := range []string{"is_nsfw", "is_filtered", "is_cert_valid", "is_cert_processed", "has_claim_list"} {
			if r[field] != nil {
				r[field] = truth(r[field])
			}
		}
		for _, field := range []string{"created_at", "modified_at"} {
			if r[field] != nil {
				r[field] = timestamp(r[field]).Format("2006-01-02T15:04:05-07:00")
			}
		}
		for _, field := range []string{"value_as_json", "claim_id_list"} {
			if str, ok := r[field].(string); ok {
				value, e := decodeOrderedJSON(str)
				if e == nil {
					r[field] = cakeJSONArray(value)
				} else {
					r[field] = nil
				}
			}
		}
	}
	return rows
}
