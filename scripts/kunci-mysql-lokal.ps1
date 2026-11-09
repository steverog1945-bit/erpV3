# Membatasi MySQL agar hanya menerima koneksi dari komputer server ini (127.0.0.1).
# Klien LAN memakai aplikasi lewat Apache; tidak ada yang perlu terhubung langsung ke port 3306.
#
# CARA PAKAI: PowerShell sebagai Administrator, lalu
#   powershell -ExecutionPolicy Bypass -File scripts\kunci-mysql-lokal.ps1
# Skrip menyalin my.ini ke my.ini.sebelum-bind, mengubah satu baris, lalu meminta Anda
# menghentikan dan menjalankan ulang MySQL dari XAMPP Control Panel (aplikasi tidak menyentuh proses MySQL).
# Batalkan: salin my.ini.sebelum-bind kembali menjadi my.ini.

param([string]$Ini = 'C:\xampp\mysql\bin\my.ini')
$ErrorActionPreference = 'Stop'

if (-not (Test-Path $Ini)) { Write-Host "GAGAL: $Ini tidak ditemukan." -ForegroundColor Red; exit 1 }
$isi = Get-Content $Ini -Raw
if ($isi -match '(?m)^\s*bind-address\s*=') { Write-Host 'bind-address sudah aktif di my.ini. Tidak ada yang diubah.'; exit 0 }

Copy-Item $Ini "$Ini.sebelum-bind" -Force
$baru = $isi -replace '(?m)^#\s*bind-address\s*=\s*"127\.0\.0\.1"\s*$', 'bind-address="127.0.0.1"'
if ($baru -eq $isi) { Write-Host 'GAGAL: baris contoh bind-address tidak ditemukan; ubah manual.' -ForegroundColor Red; exit 1 }
Set-Content -Path $Ini -Value $baru -NoNewline -Encoding ASCII

Write-Host 'Selesai. Sekarang di XAMPP Control Panel: Stop lalu Start pada MySQL.' -ForegroundColor Green
Write-Host 'Cek: php artisan produksi:cek  (baris bind_address harus OK) dan buka aplikasi seperti biasa.'
