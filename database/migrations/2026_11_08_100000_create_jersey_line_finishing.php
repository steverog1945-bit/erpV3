<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Finishing per BARIS order jersey (rengerang transaksi_detail_finishing
        // pada POS): jahit keliling per keping dst. Terpisah dari tabel lines
        // supaya jenis finishing lain bisa ditambah tanpa rombak struktur.
        if (!Schema::hasTable('jersey_line_finishing')) {
            Schema::create('jersey_line_finishing', function (Blueprint $t) {
                $t->id();
                $t->foreignId('jersey_order_line_id')->constrained('jersey_order_lines')->cascadeOnDelete();
                $t->foreignId('id_finishing')->nullable()->constrained('finishing', 'id_finishing')->nullOnDelete();
                $t->string('status_finishing', 10)->default('Standar');
                $t->decimal('qty_pakai', 14, 4)->nullable();
                $t->decimal('biaya', 15, 2)->default(0);
                $t->decimal('tarif_efektif', 15, 4)->nullable();
                $t->unsignedInteger('keping')->nullable();
                $t->decimal('lebar_potong', 10, 2)->nullable();
                $t->decimal('tinggi_potong', 10, 2)->nullable();
                $t->string('catatan', 255)->nullable();
                $t->timestamps();
            });
        }

        // tarif efektif snapshot (master tarif bisa berubah belakangan).
        if (!Schema::hasColumn('jersey_line_finishing', 'tarif_efektif')) {
            Schema::table('jersey_line_finishing', function (Blueprint $t) {
                $t->decimal('tarif_efektif', 15, 4)->nullable()->after('biaya');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jersey_line_finishing');
    }
};
