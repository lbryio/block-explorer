// Package webroot contains the unchanged frontend assets shipped in the binary.
package webroot

import "embed"

// Files includes chart/export libraries and their nested resources as well as
// the explorer's scripts, styles and images. Source files are not embedded.
//
//go:embed all:amcharts all:css all:img all:js
var Files embed.FS
