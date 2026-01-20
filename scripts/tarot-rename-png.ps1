param(
    [Parameter(Mandatory = $true)]
    [string]$SourceDir,

    # Optional different destination directory (defaults to SourceDir)
    [string]$TargetDir = $null,

    # Copy instead of rename (keeps originals)
    [switch]$Copy,

    # Safer default: show what would happen without changing files
    [switch]$DryRun,

    # Prefer mapping by leading number in filename (e.g. 0.png, 00.png, 12-foo.png)
    [switch]$MatchByNumber
)

# PSScriptAnalyzer -IgnoreRuleName PSAvoidAssignmentToAutomaticVariable

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $root 'config\tarot.php'

if (-not (Test-Path -LiteralPath $SourceDir)) {
    throw "SourceDir not found: $SourceDir"
}

if (-not (Test-Path -LiteralPath $configPath)) {
    throw "Config not found: $configPath"
}

if ([string]::IsNullOrWhiteSpace($TargetDir)) {
    $TargetDir = $SourceDir
}

if (-not (Test-Path -LiteralPath $TargetDir)) {
    throw "TargetDir not found: $TargetDir"
}

$content = Get-Content -LiteralPath $configPath -Raw -Encoding UTF8
$rx = [regex]::new("'file'\\s*=>\\s*'([^']+\\.png)'")

$targetNames = @()
$m = $rx.Match($content)
while ($m.Success) {
    $targetNames += $m.Groups[1].Value
    $m = $m.NextMatch()
}

if ($targetNames.Count -eq 0) {
    throw "No card files found in config/tarot.php (expected 'file' => 'xx.png')"
}

$sourceFiles = Get-ChildItem -LiteralPath $SourceDir -Filter '*.png' -File | Sort-Object Name
if ($sourceFiles.Count -eq 0) {
    throw "No .png files found in SourceDir: $SourceDir"
}

if ($MatchByNumber) {
    $byNumber = @{}
    foreach ($f in $sourceFiles) {
        $base = [IO.Path]::GetFileNameWithoutExtension($f.Name)
        $numMatch = [regex]::Match($base, '^(\\d{1,2})')
        if (-not $numMatch.Success) {
            continue
        }
        $n = [int]$numMatch.Groups[1].Value
        if (-not $byNumber.ContainsKey($n)) {
            $byNumber[$n] = $f
        }
    }

    $missing = @()
    for ($i = 0; $i -lt $targetNames.Count; $i++) {
        $t = $targetNames[$i]
        $nMatch = [regex]::Match($t, '^(\\d{1,2})-')
        if (-not $nMatch.Success) {
            $missing += $t
            continue
        }
        $n = [int]$nMatch.Groups[1].Value
        if (-not $byNumber.ContainsKey($n)) {
            $missing += $t
        }
    }

    if ($missing.Count -gt 0) {
        throw "MatchByNumber enabled but could not find source files for: $($missing -join ', ')\nTip: name your source PNGs starting with the card number (0..21 or 00..21)."
    }

    foreach ($t in $targetNames) {
        $n = [int]([regex]::Match($t, '^(\\d{1,2})-').Groups[1].Value)
        $src = $byNumber[$n]
        $dstPath = Join-Path $TargetDir $t

        if (Test-Path -LiteralPath $dstPath) {
            Write-Host "SKIP (exists): $dstPath"
            continue
        }

        if ($DryRun) {
            Write-Host "DRYRUN: $($src.FullName) -> $dstPath"
            continue
        }

        if ($Copy) {
            Copy-Item -LiteralPath $src.FullName -Destination $dstPath
        } else {
            if ($TargetDir -ne $SourceDir) {
                Move-Item -LiteralPath $src.FullName -Destination $dstPath
            } else {
                Rename-Item -LiteralPath $src.FullName -NewName $t
            }
        }

        Write-Host "OK: $($src.Name) -> $t"
    }

    exit 0
}

# Fallback: map by sorted order (requires you to have exactly 22 PNGs in the right order)
if ($sourceFiles.Count -ne $targetNames.Count) {
    throw "Expected $($targetNames.Count) source PNG files but found $($sourceFiles.Count).\nEither provide exactly $($targetNames.Count) PNGs in SourceDir or use -MatchByNumber." 
}

for ($i = 0; $i -lt $targetNames.Count; $i++) {
    $src = $sourceFiles[$i]
    $t = $targetNames[$i]
    $dstPath = Join-Path $TargetDir $t

    if (Test-Path -LiteralPath $dstPath) {
        Write-Host "SKIP (exists): $dstPath"
        continue
    }

    if ($DryRun) {
        Write-Host "DRYRUN: $($src.FullName) -> $dstPath"
        continue
    }

    if ($Copy) {
        Copy-Item -LiteralPath $src.FullName -Destination $dstPath
    } else {
        if ($TargetDir -ne $SourceDir) {
            Move-Item -LiteralPath $src.FullName -Destination $dstPath
        } else {
            Rename-Item -LiteralPath $src.FullName -NewName $t
        }
    }

    Write-Host "OK: $($src.Name) -> $t"
}
