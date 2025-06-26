# PowerShell script to batch convert all .jpg album covers to .webp for speed optimization
# Place this script in the 'assets/fresh&new playlist/images' directory and run it in PowerShell

$sourceDir = "$(Split-Path -Parent $MyInvocation.MyCommand.Definition)"
$destDir = Join-Path $sourceDir "..\images_webp"

if (!(Test-Path $destDir)) {
    New-Item -ItemType Directory -Path $destDir | Out-Null
}

Get-ChildItem -Path $sourceDir -Filter *.jpg | ForEach-Object {
    $jpg = $_.FullName
    $webp = Join-Path $destDir ($_.BaseName + ".webp")
    magick convert "$jpg" -quality 80 "$webp"
    Write-Host "Converted: $jpg -> $webp"
}

Write-Host "All .jpg images converted to .webp in $destDir."
