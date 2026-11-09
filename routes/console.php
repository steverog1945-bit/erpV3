<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup & Recovery (roadmap LEVEL 1 - WAJIB). Butuh `php artisan
// schedule:work` (atau Task Scheduler Windows tiap menit memanggil
// `php artisan schedule:run`) benar-benar berjalan di server produksi -
// jadwal ini sendiri tidak aktif tanpa proses scheduler tersebut.
Schedule::command('backup:run daily')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('backup:run weekly')->weeklyOn(1, '01:30')->withoutOverlapping();
// Perawatan data: cache/sesi kedaluwarsa, log audit lama ke arsip. Setelah backup harian.
Schedule::command('data:rapikan')->dailyAt('03:00')->withoutOverlapping();
// Pemeriksaan pembukuan (jurnal seimbang, Kas Kecil vs jurnal, piutang vs sisa tagihan, stok, collation).
// Temuan muncul di lonceng notifikasi dan halaman Audit Bulanan.
Schedule::command('audit:integritas')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('backup:run monthly')->monthlyOn(1, '02:00')->withoutOverlapping();
