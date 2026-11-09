<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Approval Workflow generik (roadmap LEVEL 1 - WAJIB). Prinsipnya:
 * aksi yang butuh persetujuan TIDAK dieksekusi langsung oleh pengaju -
 * cuma DIAJUKAN (ajukan()), tersimpan di approval_requests dengan status
 * Menunggu. Eksekusi SUNGGUHAN baru terjadi saat approver memanggil
 * setujui() dengan callback yang menjalankan logic bisnis yang
 * sebenarnya - service ini SENGAJA tidak tahu apa-apa soal logic tiap
 * jenis aksi (batalkan nota, dst), cuma mengurus siklus hidup
 * pengajuan/keputusannya. Pemetaan tipe_aksi -> callback eksekusi ada di
 * controller pemanggil (lihat ApprovalController::approve()).
 */
class ApprovalService
{
    public static function ajukan(
        string $tipeAksi,
        ?string $referensiType,
        ?int $referensiId,
        array $payload,
        string $ringkasan,
        ?string $alasanPengajuan = null
    ): ApprovalRequest {
        $req = ApprovalRequest::create([
            'tipe_aksi' => $tipeAksi,
            'referensi_type' => $referensiType,
            'referensi_id' => $referensiId,
            'payload' => $payload,
            'ringkasan' => $ringkasan,
            'requested_by' => Auth::id(),
            'alasan_pengajuan' => $alasanPengajuan,
            'status' => 'Menunggu',
        ]);

        // Jalan pintas di toast berikutnya: beri tahu ke mana proses berlanjut.
        session()->flash('lanjut', ['teks' => 'Menunggu persetujuan Administrator', 'url' => optional(Auth::user()->role)->nama_role === 'Administrator' ? route('approval.index') : null, 'tombol' => 'Lihat Antrian Persetujuan']);

        return $req;
    }

    /**
     * @param  \Closure(array $payload): void  $eksekusi  Dijalankan HANYA kalau pengajuan masih Menunggu, di dalam transaction yang sama dengan perubahan status approval-nya
     */
    public static function setujui(ApprovalRequest $req, \Closure $eksekusi, ?string $catatan = null): void
    {
        DB::transaction(function () use ($req, $eksekusi, $catatan) {
            // lockForUpdate() - dua approver mengklik "Setujui" nyaris
            // bersamaan pada pengajuan yang sama tidak boleh sama-sama
            // lolos & mengeksekusi aksi bisnisnya dua kali.
            $fresh = ApprovalRequest::whereKey($req->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== 'Menunggu') {
                throw new \RuntimeException('Pengajuan ini sudah diputuskan sebelumnya (status: ' . $fresh->status . ').');
            }

            // Pemisahan tugas (maker-checker): yang mengajukan tidak menyetujui
            // pengajuannya sendiri selama masih ada Administrator lain yang bisa
            // memutuskan. Bila dia satu-satunya Administrator, tetap boleh
            // (usaha kecil tidak boleh terkunci sendiri) tetapi keputusan diberi
            // catatan supaya pemeriksa tahu tidak ada orang kedua yang menilai.
            if ((int) $fresh->requested_by === (int) Auth::id()) {
                $adaPenyetujuLain = DB::table('users')
                    ->join('roles', 'roles.id_role', '=', 'users.id_role')
                    ->where('roles.nama_role', 'Administrator')
                    ->where('users.id_user', '!=', Auth::id())
                    ->exists();

                if ($adaPenyetujuLain) {
                    throw new \RuntimeException('Anda tidak bisa menyetujui pengajuan yang Anda buat sendiri. Minta Administrator lain untuk memutuskannya.');
                }

                $catatan = trim(($catatan ? $catatan . ' | ' : '') . 'Disetujui oleh pengaju sendiri (tidak ada Administrator lain).');
            }

            $eksekusi($fresh->payload ?? []);

            $fresh->update([
                'status' => 'Disetujui',
                'decided_by' => Auth::id(),
                'catatan_keputusan' => $catatan,
                'decided_at' => now(),
            ]);
        });
    }

    /**
     * @param  \Closure(array $payload): void|null  $eksekusi  Opsional - dijalankan HANYA kalau pengajuan masih Menunggu, di dalam transaction yang sama dengan perubahan status approval-nya. Dipakai jenis aksi yang entitasnya perlu status eksplisit "Ditolak" (mis. Stock Opname), bukan cuma diam di status semula seperti batalkan_nota.
     */
    public static function tolak(ApprovalRequest $req, ?string $catatan = null, ?\Closure $eksekusi = null): void
    {
        DB::transaction(function () use ($req, $catatan, $eksekusi) {
            $fresh = ApprovalRequest::whereKey($req->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== 'Menunggu') {
                throw new \RuntimeException('Pengajuan ini sudah diputuskan sebelumnya (status: ' . $fresh->status . ').');
            }

            if ($eksekusi) {
                $eksekusi($fresh->payload ?? []);
            }

            $fresh->update([
                'status' => 'Ditolak',
                'decided_by' => Auth::id(),
                'catatan_keputusan' => $catatan,
                'decided_at' => now(),
            ]);
        });
    }
}
