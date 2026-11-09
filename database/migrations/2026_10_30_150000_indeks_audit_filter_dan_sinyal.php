<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks dari audit EXPLAIN pada data besar (docs/AUDIT_TOTAL.md bagian 29): kolom yang dipakai saringan,
 * urutan, dan hitungan berkala yang sebelumnya memindai seluruh tabel.
 *  - updated_at pada tabel yang dipantau /sinyal (MAX(updated_at) tiap 30 detik per layar);
 *  - status dan tanggal yang dipakai daftar, dashboard, notifikasi, dan laporan.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> tabel => [nama indeks => kolom] */
    private const INDEKS = [
        'transaksi' => ['transaksi_status_collecting_index' => ['status_collecting', 'id_transaksi'], 'transaksi_updated_at_index' => ['updated_at']],
        'transaksi_detail' => ['transaksi_detail_urgent_spk_index' => ['status_urgent', 'status_spk'], 'transaksi_detail_updated_at_index' => ['updated_at']],
        'approval_requests' => ['approval_requests_status_created_index' => ['status', 'created_at'], 'approval_requests_updated_at_index' => ['updated_at']],
        'kas_kecil' => ['kas_kecil_tanggal_jenis_index' => ['tanggal', 'jenis']],
        'jersey_orders' => [
            'jersey_orders_order_date_index' => ['order_date'], 'jersey_orders_status_index' => ['status'],
            'jersey_orders_bayar_deadline_index' => ['status_pembayaran', 'deadline'], 'jersey_orders_deadline_index' => ['deadline'],
            'jersey_orders_updated_at_index' => ['updated_at'],
        ],
        'jersey_order_payments' => ['jersey_order_payments_tanggal_index' => ['tanggal_bayar']],
        'pengambilan' => ['pengambilan_updated_at_index' => ['updated_at']],
        'purchase_requests' => ['purchase_requests_updated_at_index' => ['updated_at']],
        'purchase_orders' => ['purchase_orders_updated_at_index' => ['updated_at']],
        'qc_inspeksi' => ['qc_inspeksi_updated_at_index' => ['updated_at']],
        'waste_log' => ['waste_log_updated_at_index' => ['updated_at']],
        'rework' => ['rework_updated_at_index' => ['updated_at']],
        'quotations' => ['quotations_updated_at_index' => ['updated_at']],
        'nota_edit_request' => ['nota_edit_request_updated_at_index' => ['updated_at']],
        'kas_harian' => ['kas_harian_updated_at_index' => ['updated_at']],
    ];

    private function ada(string $tabel, string $nama): bool
    {
        return collect(DB::select("show index from `{$tabel}` where Key_name = ?", [$nama]))->isNotEmpty();
    }

    public function up(): void
    {
        foreach (self::INDEKS as $tabel => $daftar) {
            if (!Schema::hasTable($tabel)) {
                continue;
            }
            foreach ($daftar as $nama => $kolom) {
                if ($this->ada($tabel, $nama) || !Schema::hasColumns($tabel, $kolom)) {
                    continue;
                }
                Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom, $nama));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEKS as $tabel => $daftar) {
            foreach (array_keys($daftar) as $nama) {
                if (Schema::hasTable($tabel) && $this->ada($tabel, $nama)) {
                    Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($nama));
                }
            }
        }
    }
};
