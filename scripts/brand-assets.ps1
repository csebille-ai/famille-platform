$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Drawing

$root = (Get-Location).Path
$src = Join-Path $root 'public\images\logo1.png'
if (!(Test-Path $src)) {
    throw "Missing source image: $src"
}

$brandDir = Join-Path $root 'public\images\brand'
if (!(Test-Path $brandDir)) {
    New-Item -ItemType Directory -Path $brandDir | Out-Null
}

$img = [System.Drawing.Image]::FromFile($src)
Write-Host "Source: $($img.Width)x$($img.Height)"

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
