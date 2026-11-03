package explorer

type pagination struct {
	Visible                    bool
	Previous, Next             int
	Pages                      []int
	StartEllipsis, EndEllipsis bool
	Last, Current              int
}

func paginate(records, limit, page int) (int, int, pagination) {
	total := (records + limit - 1) / limit
	if page < 1 {
		page = 1
	}
	if page > total {
		page = total
	}
	p := pagination{Visible: total > 1, Last: total, Current: page}
	if page > 1 {
		p.Previous = page - 1
	}
	if page < total {
		p.Next = page + 1
	}
	start, end := 1, min(total, 10)
	if total > 10 && page > 5 {
		if page >= 10 {
			start = page - 5
		}
		if page > total-10 && start > 5 {
			end = total
		} else {
			end = min(page+5, total)
		}
	}
	if records > 0 {
		for i := start; i <= end; i++ {
			p.Pages = append(p.Pages, i)
		}
		p.StartEllipsis = start >= 5
		p.EndEllipsis = end < total-1
	}
	return page, total, p
}
