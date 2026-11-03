package explorer

import (
	"fmt"
	"strings"
	"time"
)

func unitString(n int, u string) string {
	if n != 1 {
		u += "s"
	}
	return fmt.Sprintf("%d %s", n, u)
}
func cakeAgo(t, now time.Time) string {
	diff := int64(now.Unix() - t.Unix())
	future := diff < 0
	if future {
		diff = -diff
	}
	if diff == 0 {
		return "just now"
	}
	if diff > int64(now.AddDate(0, 1, 0).Sub(now)/time.Second) {
		return "on " + t.Format("1/2/06")
	}
	vals := []int{0, 0, 0, 0, 0, 0, 0}
	units := []string{"year", "month", "week", "day", "hour", "minute", "second"}
	if diff >= 604800 {
		past, next := t, now
		if future {
			past, next = now, t
		}
		years := next.Year() - past.Year()
		months := int(next.Month()) + 12*years - int(past.Month())
		if months >= 12 {
			years = months / 12
			months -= years * 12
		}
		if next.Month() < past.Month() && next.Year()-past.Year() == 1 {
			years--
		}
		days := next.Day() - past.Day()
		if days < 0 {
			daysInPast := time.Date(past.Year(), past.Month()+1, 0, 0, 0, 0, 0, time.UTC).Day()
			daysInFuture := time.Date(next.Year(), next.Month(), 0, 0, 0, 0, 0, time.UTC).Day()
			if !future {
				days = daysInPast - past.Day() + next.Day()
			} else {
				days = daysInFuture - past.Day() + next.Day()
			}
			if next.Month() != past.Month() {
				months--
			}
		}
		if months == 0 && years >= 1 && diff < int64(years)*31536000 {
			months = 11
			years--
		}
		if months >= 12 {
			years++
			months -= 12
		}
		vals[0], vals[1], vals[2], vals[3] = years, months, days/7, days%7
	} else {
		vals[3] = int(diff / 86400)
		diff %= 86400
		vals[4] = int(diff / 3600)
		diff %= 3600
		vals[5] = int(diff / 60)
		vals[6] = int(diff % 60)
	}
	first := 6
	for i, n := range vals {
		if n > 0 {
			first = i
			break
		}
	}
	end := 6
	if first <= 2 {
		end = 3
	} else if first == 3 {
		end = 4
	} else if first <= 5 {
		end = 5
	}
	pieces := []string{}
	for i := 0; i <= end; i++ {
		if vals[i] > 0 {
			pieces = append(pieces, unitString(vals[i], units[i]))
		}
	}
	out := strings.Join(pieces, ", ")
	if out == "" {
		article := "a"
		if units[end] == "hour" {
			article = "an"
		}
		if future {
			return "in about " + article + " " + units[end]
		}
		return "about " + article + " " + units[end] + " ago"
	}
	if !future {
		out += " ago"
	}
	return out
}
func humanAgo(t, now time.Time) string {
	past, next := t, now
	future := t.After(now)
	if future {
		past, next = now, t
	}
	years := next.Year() - past.Year()
	months := int(next.Month()) - int(past.Month())
	if months < 0 {
		years--
		months += 12
	}
	days := next.Day() - past.Day()
	if next.Hour()*3600+next.Minute()*60+next.Second() < past.Hour()*3600+past.Minute()*60+past.Second() {
		days--
	}
	if days < 0 {
		months--
		if months < 0 {
			years--
			months += 12
		}
		days += time.Date(past.Year(), past.Month()+1, 0, 0, 0, 0, 0, time.UTC).Day()
	}
	seconds := int(next.Sub(past) / time.Second)
	n, unit := seconds%60, "second"
	if years > 0 {
		n, unit = years, "year"
	} else if months > 0 {
		n, unit = months, "month"
	} else if days >= 7 {
		n, unit = days/7, "week"
	} else if days > 0 {
		n, unit = days, "day"
	} else if seconds >= 3600 {
		n, unit = seconds/3600, "hour"
	} else if seconds >= 60 {
		n, unit = seconds/60, "minute"
	}
	out := unitString(n, unit)
	if future {
		return out + " from now"
	}
	return out + " ago"
}
