<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Halaman baca-saja untuk menelusuri audit_logs (lihat App\Services\
 * AuditLogger untuk penjelasan lengkap apa yang tercakup & tidak).
 * Sengaja tanpa create/update/delete - satu-satunya jalur tulis tabel ini
 * adalah AuditLogger::log(), bukan lewat controller ini.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // "Arsip": log lama yang sudah dipindahkan oleh data:rapikan (struktur tabel sama).
        $arsip = $request->boolean('arsip') && \Illuminate\Support\Facades\Schema::hasTable('audit_logs_arsip');
        $model = $arsip ? (new AuditLog)->setTable('audit_logs_arsip') : new AuditLog;
        $query = $model->newQuery()->with('user')->orderByDesc('created_at');

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->auditable_type . '%');
        }
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        // Perf dataset besar: created_at = TIMESTAMP + sudah terindeks -
        // whereDate()/DATE() mematikan index; pakai rentang sargable.
        if ($request->filled('awal')) {
            $query->where('created_at', '>=', $request->awal . ' 00:00:00');
        }
        if ($request->filled('akhir')) {
            $query->where('created_at', '<', \Carbon\Carbon::parse($request->akhir)->addDay()->format('Y-m-d') . ' 00:00:00');
        }

        $logs = $query->paginate(20)->withQueryString();

        $daftarTipe = $model->newQuery()->select('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type');

        return view('audit-log.index', compact('logs', 'daftarTipe', 'arsip'));
    }
}
