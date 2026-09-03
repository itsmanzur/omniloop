param(
	[string]$Version = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$pluginRoot = Split-Path -Parent $PSScriptRoot
$pluginFile = Join-Path $pluginRoot "omniloop.php"
$outDir = Join-Path $pluginRoot "languages"
$outFile = Join-Path $outDir "omniloop.pot"

if ([string]::IsNullOrWhiteSpace($Version)) {
	$header = Get-Content -LiteralPath $pluginFile -Raw
	$detected = [regex]::Match($header, "Version:\s*([\d.]+)")
	if (-not $detected.Success) {
		throw "Could not detect Version from omniloop.php"
	}
	$Version = $detected.Groups[1].Value
}

$excludePrefixes = @(
	"dist/",
	".git/",
	"vendor/",
	"tests/",
	"node_modules/"
)

$pattern = "(?<func>__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'(?<msg>(?:\\'|[^'])*)'\s*,\s*'omniloop'"
$phpFiles = Get-ChildItem -Path $pluginRoot -Recurse -Filter *.php -File | Where-Object {
	$relative = $_.FullName.Substring($pluginRoot.Length + 1).Replace("\", "/")
	foreach ($prefix in $excludePrefixes) {
		if ($relative.StartsWith($prefix)) {
			return $false
		}
	}
	if ($relative -match "(^|/)(dist|vendor|tests|node_modules|\.git)(/|$)") {
		return $false
	}
	return $true
}
$entries = [System.Collections.Generic.Dictionary[string, System.Collections.Generic.List[string]]]::new()

foreach ($file in $phpFiles) {
	$relative = $file.FullName.Substring($pluginRoot.Length + 1).Replace("\", "/")
	$lines = Get-Content -LiteralPath $file.FullName

	for ($i = 0; $i -lt $lines.Count; $i++) {
		$line = $lines[$i]
		$matches = [regex]::Matches($line, $pattern)
		foreach ($match in $matches) {
			$msg = $match.Groups["msg"].Value.Replace("\'", "'")
			if (-not $entries.ContainsKey($msg)) {
				$entries[$msg] = [System.Collections.Generic.List[string]]::new()
			}
			$entries[$msg].Add(("{0}:{1}" -f $relative, ($i + 1)))
		}
	}
}

New-Item -ItemType Directory -Path $outDir -Force | Out-Null

$header = @(
	'msgid ""',
	'msgstr ""',
	('"Project-Id-Version: OmniLoop for Divi {0}\n"' -f $Version),
	'"Report-Msgid-Bugs-To: \n"',
	('"POT-Creation-Date: {0}\n"' -f (Get-Date -Format "yyyy-MM-dd HH:mmK")),
	'"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"',
	'"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"',
	'"Language-Team: LANGUAGE <LL@li.org>\n"',
	'"MIME-Version: 1.0\n"',
	'"Content-Type: text/plain; charset=UTF-8\n"',
	'"Content-Transfer-Encoding: 8bit\n"',
	'"X-Domain: omniloop\n"',
	''
)

$body = foreach ($msg in ($entries.Keys | Sort-Object)) {
	$refs = $entries[$msg] | Select-Object -Unique
	'#: ' + ($refs -join ' ')
	'msgid "' + ($msg.Replace('"', '\"')) + '"'
	'msgstr ""'
	''
}

($header + $body) | Set-Content -LiteralPath $outFile -Encoding UTF8

Write-Host ("Exported POT: {0} (version {1})" -f $outFile, $Version)
