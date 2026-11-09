<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\AuditLogger;
use App\Services\JamKerjaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Pengaturan Jam Kerja: hari buka/tutup + jam mulai/selesai per hari, dipakai untuk memblokir transaksi kas di hari libur. */
class JamKerjaController extends Controller
{
    public function index()
    {
        return view('pengaturan.jam-kerja', [
            'jam' => JamKerjaService::semua(),
            'batasi' => (bool) AppSetting::current()->batasi_jam_kerja,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'jam' => 'required|array',
            'jam.*.mulai' => 'nullable|date_format:H:i',
            'jam.*.selesai' => 'nullable|date_format:H:i',
        ]);

        foreach (JamKerjaService::HARI as $no => $nama) {
            $in = $request->input("jam.{$no}", []);
            $buka = isset($in['buka']);
            $mulai = $in['mulai'] ?? '08:00';
            $selesai = $in['selesai'] ?? '17:00';
            if ($buka && $selesai <= $mulai) {
                return back()->withInput()->with('error', "Jam selesai {$nama} harus lebih besar dari jam mulai.");
            }
            DB::table('jam_kerja')->updateOrInsert(['hari' => $no], ['buka' => $buka, 'jam_mulai' => $mulai . ':00', 'jam_selesai' => $selesai . ':00']);
        }

        AppSetting::current()->update(['batasi_jam_kerja' => $request->boolean('batasi_jam_kerja')]);
        AuditLogger::log('updated', 'jam_kerja', null, null, null, 'Pengaturan jam kerja diperbarui');

        return redirect()->route('jam-kerja.index')->with('success', 'Jam kerja disimpan.');
    }
}
