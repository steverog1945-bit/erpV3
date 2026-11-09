# Memasang penjadwal Laravel di Windows Task Scheduler.
#
# KENAPA PERLU: jadwal backup harian/mingguan/bulanan ada di routes/console.php,
# tetapi Laravel hanya menjalankannya bila `php artisan schedule:run` dipanggil
# tiap menit oleh sesuatu di luar aplikasi. Tanpa tugas ini TIDAK ADA backup
# otomatis yang pernah berjalan (diperiksa: satu-satunya backup adalah yang
# dibuat manual).
#
# CARA PAKAI (sekali saja): buka PowerShell sebagai Administrator, lalu
#   cd C:\xampp\htdocs\grafikaorion
#   powershell -ExecutionPolicy Bypass -File scripts\pasang-penjadwal.ps1
#
# Skrip ini aman dijalankan ulang: tugas lama dengan nama yang sama diganti.
# Untuk mencabut:  Unregister-ScheduledTask -TaskName 'ERP Percetakan - Penjadwal Laravel' -Confirm:$false

param(
    [string]$Php = 'C:\xampp\php\php.exe',
    [string]$Proyek = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$Nama = 'ERP Percetakan - Penjadwal Laravel'
)

$ErrorActionPreference = 'Stop'

$adalahAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $adalahAdmin) {
    Write-Host 'GAGAL: jalankan PowerShell sebagai Administrator.' -ForegroundColor Red
    exit 1
}
if (-not (Test-Path $Php)) { Write-Host "GAGAL: php.exe tidak ditemukan di $Php" -ForegroundColor Red; exit 1 }
if (-not (Test-Path (Join-Path $Proyek 'artisan'))) { Write-Host "GAGAL: artisan tidak ditemukan di $Proyek" -ForegroundColor Red; exit 1 }

if (Get-ScheduledTask -TaskName $Nama -ErrorAction SilentlyContinue) {
    Unregister-ScheduledTask -TaskName $Nama -Confirm:$false
    Write-Host "Tugas lama '$Nama' diganti."
}

$aksi = New-ScheduledTaskAction -Execute $Php -Argument 'artisan schedule:run' -WorkingDirectory $Proyek
$pemicu = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$pengaturan = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
# SYSTEM: berjalan tanpa perlu ada yang login dan tanpa jendela hitam yang muncul tiap menit.
$pelaku = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName $Nama -Action $aksi -Trigger $pemicu -Settings $pengaturan -Principal $pelaku `
    -Description 'Menjalankan php artisan schedule:run tiap menit (backup harian 01:00, mingguan, bulanan).' | Out-Null

Start-ScheduledTask -TaskName $Nama
Start-Sleep -Seconds 5
$info = Get-ScheduledTaskInfo -TaskName $Nama

Write-Host ''
Write-Host "Terpasang: '$Nama'" -ForegroundColor Green
Write-Host "  Terakhir jalan : $($info.LastRunTime)  (hasil: $($info.LastTaskResult); 0 = sukses)"
Write-Host "  Berikutnya     : $($info.NextRunTime)"
Write-Host ''
Write-Host 'VERIFIKASI besok pagi:'
Write-Host '  php artisan produksi:cek          (baris Backup harus OK)'
Write-Host '  dir storage\app\backups\daily     (harus ada berkas backup_daily_*.sql baru)'
