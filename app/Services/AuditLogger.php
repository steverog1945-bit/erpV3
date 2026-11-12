<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur penulisan ke audit_logs - dipakai baik oleh trait
 * Auditable (model Eloquent, otomatis) maupun dipanggil manual langsung
 * dari controller yang masih pakai DB::table() (mayoritas app ini).
 *
 * PENTING - keterbatasan yang harus dipahami: trait Auditable HANYA
 * menangkap perubahan lewat Eloquent (Model::create()/update()/delete()).
 * Controller yang menulis lewat DB::table()->insert()/update()/delete()
 * TIDAK otomatis tercatat - itu sebabnya method log() di sini bersifat
 * publik & sengaja dibuat mudah dipanggil manual. Baru menerapkan
 * Auditable + memanggil log() manual DI TITIK MUTASI YANG SPESIFIK
 * membuat baris itu benar-benar tercatat - menambah model/trait saja
 * TIDAK otomatis mengaudit apapun kalau controllernya masih DB::table().
 */
class AuditLogger
{
    public static function log(
        string $event,
        string $auditableType,
        ?int $auditableId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $keterangan = null
    ): void {
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user?->id_user,
            'user_label' => $user?->username,
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'keterangan' => $keterangan,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => request()?->fullUrl(),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Pencatatan master data yang ditulis lewat DB::table(): baca baris
     * SESUDAH aksi lalu catat lama -> baru. Untuk 'deleted' tidak ada baris
     * baru. Panggil di dalam transaksi pemanggil supaya ikut rollback.
     */
    public static function master(string $event, string $tabel, string $kunci, int $id, ?object $lama = null, ?string $keterangan = null): void
    {
        $baru = $event === 'deleted' ? null : (array) DB::table($tabel)->where($kunci, $id)->first();

        self::log($event, $tabel, $id, $lama ? (array) $lama : null, $baru, $keterangan);
    }
}
