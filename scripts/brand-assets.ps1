$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Drawing

$root = (Get-Location).Path
$srcMaison = Join-Path $root 'public\images\brand\maison.png'
$srcDefault = Join-Path $root 'public\images\logo1.png'

$src = $srcDefault
if (Test-Path $srcMaison) {
    $src = $srcMaison
}

if (!(Test-Path $src)) {
    throw "Missing source image: $src"
}

$brandDir = Join-Path $root 'public\images\brand'
if (!(Test-Path $brandDir)) {
    New-Item -ItemType Directory -Path $brandDir | Out-Null
}

$img = [System.Drawing.Image]::FromFile($src)
Write-Host "Source: $($img.Width)x$($img.Height)"

$wordmarkPng = Join-Path $brandDir 'wm.png'
$wordmarkSrc = $src
if (Test-Path $wordmarkPng) {
    $wordmarkSrc = $wordmarkPng
}

function Save-ResizedPng {
    param(
        [Parameter(Mandatory=$true)][int]$Size,
        [Parameter(Mandatory=$true)][string]$OutPath
    )

    $bmp = New-Object System.Drawing.Bitmap $Size, $Size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $g.Clear([System.Drawing.Color]::Transparent)
    $g.DrawImage($img, 0, 0, $Size, $Size)
    $g.Dispose()

    $bmp.Save($OutPath, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()

    Write-Host "Wrote $OutPath"
}

Save-ResizedPng -Size 64  -OutPath (Join-Path $brandDir 'icon-64.png')
Save-ResizedPng -Size 192 -OutPath (Join-Path $brandDir 'icon-192.png')
Save-ResizedPng -Size 512 -OutPath (Join-Path $brandDir 'icon-512.png')
Save-ResizedPng -Size 32  -OutPath (Join-Path $root 'public\favicon-32.png')

# Regenerate SVGs with embedded PNG. Some browsers won’t load external <image href="/path"> inside an <img>-loaded SVG.
$b64Icon = [Convert]::ToBase64String([IO.File]::ReadAllBytes($src))
$dataUriIcon = "data:image/png;base64,$b64Icon"

$b64Wm = [Convert]::ToBase64String([IO.File]::ReadAllBytes($wordmarkSrc))
$dataUriWm = "data:image/png;base64,$b64Wm"

$iconSvgPath = Join-Path $brandDir 'icon.svg'
$wordmarkSvgPath = Join-Path $brandDir 'wordmark.svg'

$iconSvg = @"
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
    <title>La Famille — Icon</title>
    <image href="$dataUriIcon" x="0" y="0" width="512" height="512" preserveAspectRatio="xMidYMid meet" />
</svg>
"@

$wordmarkSvg = @"
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="512" viewBox="0 0 1600 512">
    <title>La Famille — Wordmark</title>
    <image href="$dataUriWm" x="0" y="0" width="1600" height="512" preserveAspectRatio="xMidYMid meet" />
</svg>
"@

Set-Content -Path $iconSvgPath -Value $iconSvg -Encoding UTF8
Write-Host "Wrote $iconSvgPath"
Set-Content -Path $wordmarkSvgPath -Value $wordmarkSvg -Encoding UTF8
Write-Host "Wrote $wordmarkSvgPath"

# Try to generate a basic favicon.ico (single-size) from the 32x32 png.
try {
    $bmp32Path = Join-Path $root 'public\favicon-32.png'
    $bmp32 = New-Object System.Drawing.Bitmap $bmp32Path
    $icon = [System.Drawing.Icon]::FromHandle($bmp32.GetHicon())
    $icoPath = Join-Path $root 'public\favicon.ico'
    $fs = [System.IO.File]::Open($icoPath, [System.IO.FileMode]::Create)
    $icon.Save($fs)
    $fs.Close()
    $bmp32.Dispose()
    Write-Host "Wrote $icoPath"
} catch {
    Write-Warning "Could not write favicon.ico: $($_.Exception.Message)"
}

$img.Dispose()
