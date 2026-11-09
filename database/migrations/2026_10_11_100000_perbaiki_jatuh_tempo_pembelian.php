<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan jatuh tempo faktur tempo yang tersimpan tidak konsisten: tanggal jatuh tempo dulu dihitung dari
 * tanggal isian form saat halaman dibuka (bukan tanggal transaksi yang belakangan diubah), atau kosong sama sekali.
 * Aturan yang benar: jatuh tempo = tanggal transaksi + durasi (default 30 hari). Hanya faktur tempo yang belum lunas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('pembelian_bahan')->where('tipe_pembelian', 'Tempo')->where('status_pembayaran', 'Hutang')->whereNull('deleted_at')->get() as $p) {
            $hari = max(1, (int) ($p->jatuh_tempo_hari ?: 30));
            $benar = \Carbon\Carbon::parse($p->tgl_transaksi)->addDays($hari)->toDateString();
            if ($p->jatuh_tempo_tgl !== $benar || (int) $p->jatuh_tempo_hari !== $hari) {
                DB::table('pembelian_bahan')->where('id_pembelian', $p->id_pembelian)->update(['jatuh_tempo_hari' => $hari, 'jatuh_tempo_tgl' => $benar]);
            }
        }
    }

    public function down(): void
    {
    }
};
