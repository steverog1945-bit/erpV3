<?php

namespace App\Exceptions;

/**
 * Dilempar AccountingService::postJournal() saat tanggal jurnal jatuh di
 * periode yang sudah ditutup. Turunan RuntimeException supaya semua
 * try/catch RuntimeException/Exception yang sudah ada tetap menangkapnya;
 * yang lolos sampai ke handler global ditampilkan sebagai pesan ramah
 * (lihat bootstrap/app.php), bukan halaman error 500.
 */
class PeriodeAkuntansiTertutupException extends \RuntimeException
{
}
