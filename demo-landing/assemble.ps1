# Rebuild index.html from section files. ASCII-safe UTF-8, no BOM.
$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $root "sections"))) {
	$root = $PSScriptRoot
}

$files = @(
	"01-nav.html",
	"02-hero.html",
	"03-proof.html",
	"03-why.html",
	"04-live.html",
	"05-layouts.html",
	"06-cards.html",
	"07-builder.html",
	"08-merch.html",
	"09-seo.html",
	"10-features.html",
	"11-faq.html",
	"12-cta.html"
)

$head = @"
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>OmniLoop for Divi - landing demo</title>
	<meta name="description" content="OmniLoop for Divi: the Divi 5 shop layer. One module, custom index, merchandising, pretty SEO URLs, WooCommerce optional.">
	<link rel="stylesheet" href="assets/css/landing.css">
	<script defer src="assets/js/landing.js"></script>
</head>
<body>
"@

$parts = foreach ($name in $files) {
	Get-Content -LiteralPath (Join-Path $root "sections\$name") -Raw -Encoding UTF8
}

$html = $head + ($parts -join "`n") + "`n</body>`n</html>`n"
$out = Join-Path $root "index.html"
$utf8 = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($out, $html, $utf8)
Write-Host ("Wrote {0}" -f $out)
