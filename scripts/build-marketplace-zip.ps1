param(
	[string]$Version = "2.0.0"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginRoot = Split-Path -Parent $PSScriptRoot
$pluginSlug = "omniloop"
$stageRoot = Join-Path $pluginRoot "dist"
$tempRoot = Join-Path $stageRoot "_package"
$stageDir = Join-Path $tempRoot $pluginSlug
$legacyStageDir = Join-Path $stageRoot $pluginSlug
$zipPath = Join-Path $stageRoot ("{0}-marketplace-{1}.zip" -f $pluginSlug, $Version)

$excludeTopLevel = @(
	".git",
	".gitignore",
	"docs-assets",
	"dist",
	"node_modules",
	"omniloop_technical_blueprint.html"
)

$excludeRelative = @(
	"app/Integrations/Divi5/visual-builder/node_modules",
	"app/Integrations/Divi5/visual-builder/package.json",
	"app/Integrations/Divi5/visual-builder/package-lock.json",
	"app/Integrations/Divi5/visual-builder/webpack.config.js"
)

function Remove-IfExists {
	param([string]$Path)

	if (Test-Path -LiteralPath $Path) {
		Remove-Item -LiteralPath $Path -Recurse -Force
	}
}

function Copy-Tree {
	param(
		[string]$Source,
		[string]$Destination
	)

	New-Item -ItemType Directory -Path $Destination -Force | Out-Null

	Get-ChildItem -LiteralPath $Source -Force | ForEach-Object {
		if ($excludeTopLevel -contains $_.Name) {
			return
		}

		$target = Join-Path $Destination $_.Name
		if ($_.PSIsContainer) {
			Copy-Tree -Source $_.FullName -Destination $target
			return
		}

		Copy-Item -LiteralPath $_.FullName -Destination $target -Force
	}
}

New-Item -ItemType Directory -Path $stageRoot -Force | Out-Null
New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
Remove-IfExists -Path $stageDir
Remove-IfExists -Path $legacyStageDir
Remove-IfExists -Path $zipPath

Copy-Tree -Source $pluginRoot -Destination $stageDir

foreach ($relative in $excludeRelative) {
	Remove-IfExists -Path (Join-Path $stageDir $relative)
}

if (-not (Test-Path -LiteralPath (Join-Path $stageDir "omniloop.php"))) {
	throw "Marketplace stage is missing omniloop.php"
}

if (-not (Test-Path -LiteralPath (Join-Path $stageDir "license.txt"))) {
	throw "Marketplace stage is missing license.txt"
}

[System.IO.Compression.ZipFile]::CreateFromDirectory($tempRoot, $zipPath)

Remove-IfExists -Path $tempRoot

Write-Host ("Created Marketplace ZIP: {0}" -f $zipPath)
