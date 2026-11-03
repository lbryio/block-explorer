package explorer

import "math"

// PHP 8.5 half-up rounding compares the unscaled number with a reconstructed
// half boundary. This avoids decimal-scaling errors such as 1.005*100 < 100.5.
// Algorithm adapted from PHP Group's _php_math_round, PHP License 3.01:
// https://github.com/php/php-src/blob/PHP-8.5/ext/standard/math.c
// Copyright (c) The PHP Group. See licenses/PHP-3.01.txt.
func roundPHP(value float64, places int) float64 {
	if value == 0 {
		return 0
	}
	if math.IsNaN(value) || math.IsInf(value, 0) {
		return value
	}
	sign := 1.0
	if value < 0 {
		sign = -1
		value = -value
	}
	scale := math.Pow10(places)
	integral := math.Floor(value * scale)
	if (integral+1)/scale == value {
		integral++
	}
	if integral >= 1e16 {
		return sign * value
	}
	if value >= (integral+.5)/scale {
		integral++
	}
	result := integral / scale
	if result == 0 {
		return 0
	}
	return sign * result
}
