<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Keuangan Jersey - piutang/DP/pelunasan order jersey dicatat di
 * ledger sendiri, TERPISAH dari kas_harian & transaksi Kasir utama
 * (permintaan eksplisit: "jangan bercampur dengan kas pada kasir utama").
 * Satu order bisa punya banyak baris pembayaran (DP pertama, cicilan,
 * pelunasan, dst) - bukan lagi satu angka deposit tunggal yang diketik
 * manual seperti sebelumnya. jersey_orders.deposit & status_pembayaran
 * tetap ada (kompatibel dengan tampilan lama) tapi sekarang jadi nilai
 * TURUNAN yang dihitung ulang otomatis dari jumlah baris di tabel ini
 * (lihat JerseyOrder::recalculatePembayaran()), bukan diisi manual lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jersey_order_payments')) {
            Schema::create('jersey_order_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_order_id')->constrained('jersey_orders')->cascadeOnDelete();
                $table->date('tanggal_bayar');
                $table->decimal('jumlah', 12, 2);
                $table->enum('metode_bayar', ['Tunai', 'Transfer', 'QRIS', 'Lainnya'])->default('Tunai');
                $table->string('bukti_path')->nullable();
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jersey_order_payments');
    }
};
