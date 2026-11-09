<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * jobs/failed_jobs/job_batches/password_reset_tokens hilang total akibat
 * insiden migrate:fresh yang tidak disengaja hari ini (lihat catatan
 * lengkap di 2026_09_12_030000_restore_missing_timestamps_on_transaksi.php)
 * - tidak ada migrasi untuk tabel-tabel ini sebelumnya di project ini
 * (kemungkinan dibuat manual/dari scaffold awal, bukan lewat migrasi).
 *
 * .env project ini pakai QUEUE_CONNECTION=database & SESSION_DRIVER=
 * database - sessions & cache sudah pulih lewat migrasi lain, tapi jobs/
 * failed_jobs/password_reset_tokens belum py migrasi sama sekali. Dibuat
 * di sini mengikuti struktur standar Laravel (bukan dikarang) supaya ada
 * jejak migrasi resmi untuk instalasi/restore berikutnya - sebelumnya
 * TIDAK ADA migrasi untuk tabel-tabel ini sama sekali di project ini,
 * itulah salah satu penyebab tidak ikut terbangun ulang saat fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('password_reset_tokens');
    }
};
