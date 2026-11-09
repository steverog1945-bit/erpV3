<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Services\BackupService;

/**
 * Administrator-only: status & riwayat backup, plus tombol jalankan
 * backup manual (roadmap LEVEL 1 - Backup & Recovery).
 */
class BackupController extends Controller
{
    public function index()
    {
        $terakhirPerTipe = [];
        foreach (['daily', 'weekly', 'monthly'] as $tipe) {
            $terakhirPerTipe[$tipe] = BackupLog::where('tipe', $tipe)->orderByDesc('started_at')->first();
        }

        $riwayat = BackupLog::orderByDesc('started_at')->paginate(20);

        return view('backup.index', compact('terakhirPerTipe', 'riwayat'));
    }

    public function jalankan()
    {
        try {
            $log = BackupService::run('manual');
            return back()->with('success', "Backup manual berhasil dibuat (" . angka($log->ukuran_bytes / 1024, 1) . " KB).");
        } catch (\Throwable $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e, 'Backup manual gagal'));
        }
    }
}
