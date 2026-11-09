# sync-to-copy.ps1
# Mirrors grafikaorionV2 (proyek utama) ke grafikaorion (produksi).
# Aturan aman:
#   - .env copy / data runtime TIDAK PERNAH diubah (list di bawah).
#   - storage TIDAK di-MIR; seluruh storage dianggap milik produksi supaya
#     bukti pelanggan di produksi tidak pernah ikut terhapus.
#   - File backup *.bak-* di copy juga dipertahankan (tidak dihapus oleh mirror).
# Pakai: powershell -ExecutionPolicy Bypass -File tools\sync-to-copy.ps1 [-WhatIf]
# robocopy /MIR -> copy + hapus file yang sudah tidak ada di sumber.
# Exit code robocopy 0-7 = sukses, >=8 = error.

param(
    [string]$Source = 'C:\xampp\htdocs\grafikaorionV2',
    [string]$Target = 'C:\xampp\htdocs\grafikaorion'
)

$excludeDirs = @(
    '.git', '.agents', '.aider-desk', '.claude', '.codeartsdoer', '.hermes', '.qwen', '.zencoder',
    'node_modules', 'storage', 'tools', 'antivirus',
    'anti-slop', 'anti-slop-main',
    (Join-Path 'bootstrap' 'cache'),
    (Join-Path 'public' 'storage'),
    (Join-Path 'public' 'storage-old2')
)

$excludeFiles = @('.env', '.env.*', '*.bak-*', '*.sqlite', 'sync-to-copy.ps1')

robocopy $Source $Target /MIR /XD @($excludeDirs | ForEach-Object { Join-Path $Source $_ }) /XF .env .env.backup .env.save *.bak *.sqlite sync-to-copy.ps1 grafikaorion.zip /NFL /NDL /NJH /R:1 /W:1 @args

# Upload ikut dipindahkan TANPA /MIR (hanya menambah/memperbarui, tidak pernah
# menghapus file yang hanya ada di target). Ini yang membuat foto profil,
# thumbnail, dan bukti bayar tetap ada saat proyek berpindah komputer.
robocopy (Join-Path $Source 'storage\app\public') (Join-Path $Target 'storage\app\public') /E /NFL /NDL /NJH /NJS /R:1 /W:1 /XF .gitignore

$code = $LASTEXITCODE
if ($code -le 7) {
    Write-Host "SYNC OK (robocopy exit $code)"
    exit 0
} else {
    Write-Error "SYNC GAGAL (robocopy exit $code)"
    exit $code
}
