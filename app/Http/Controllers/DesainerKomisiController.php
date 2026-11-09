<?php

namespace App\Http\Controllers;

use App\Services\DesainKomisiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Biaya Desain per operator (monitoring + komisi/bagi hasil) dan pengaturan kelas desainer.
 * Operator Desain hanya melihat baris miliknya sendiri dan tidak bisa membayar komisi.
 */
class DesainerKomisiController extends Controller
{
    private function hanyaMilikSendiri(): bool
    {
        return optional(auth()->user()->role)->nama_role === DesainKomisiService::ROLE_DESAIN;
    }

    private function dasar(Request $request, string $dari, string $sampai)
    {
        $q = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->leftJoin('users as u', 'u.id_user', '=', 'd.id_desainer')
            ->leftJoin('karyawan as kw', 'kw.id_karyawan', '=', 'u.id_karyawan')
            ->leftJoin('kelas_desainer as c', 'c.id_kelas', '=', 'u.id_kelas_desainer')
            ->where('d.biaya_desain', '>', 0)
            ->where('t.status_nota', '!=', 'Dibatalkan')->where('d.status_spk', '!=', 'Batal')
            // Perf dataset besar: rentang sargable (DATE() mematikan index).
            ->where('t.tgl_order', '>=', $dari . ' 00:00:00')->where('t.tgl_order', '<', \Carbon\Carbon::parse($sampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($this->hanyaMilikSendiri()) {
            $q->where('d.id_desainer', auth()->id());
        } elseif ($request->filled('id_desainer')) {
            $request->id_desainer === 'tanpa' ? $q->whereNull('d.id_desainer') : $q->where('d.id_desainer', (int) $request->id_desainer);
        }
        if ($request->filled('id_kelas')) {
            $q->where('u.id_kelas_desainer', (int) $request->id_kelas);
        }
        if (in_array($request->status_komisi, ['Belum', 'Dibayar'], true)) {
            $q->where('d.komisi_status', $request->status_komisi);
        }

        return $q;
    }

    public function laporan(Request $request)
    {
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->startOfMonth()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        $milikSendiri = $this->hanyaMilikSendiri();

        $rekap = $this->dasar($request, $tglDari, $tglSampai)
            ->groupBy('d.id_desainer', 'kw.nama_karyawan', 'u.username', 'c.nama_kelas', 'c.persen_komisi')
            ->selectRaw("d.id_desainer, COALESCE(kw.nama_karyawan, u.username) as nama, c.nama_kelas, c.persen_komisi,
                COUNT(*) as jumlah_item, COUNT(DISTINCT t.id_transaksi) as jumlah_nota, SUM(d.biaya_desain) as total_biaya, SUM(d.komisi_desain) as total_komisi,
                SUM(CASE WHEN d.komisi_status = 'Belum' THEN d.komisi_desain ELSE 0 END) as komisi_belum,
                SUM(CASE WHEN d.komisi_status = 'Dibayar' THEN d.komisi_desain ELSE 0 END) as komisi_dibayar")
            ->orderByDesc('total_biaya')->get();

        $total = (object) [
            'item' => $rekap->sum('jumlah_item'), 'biaya' => (float) $rekap->sum('total_biaya'), 'komisi' => (float) $rekap->sum('total_komisi'),
            'belum' => (float) $rekap->sum('komisi_belum'), 'dibayar' => (float) $rekap->sum('komisi_dibayar'),
        ];
        $total->bersih = $total->biaya - $total->komisi;

        $data = $this->dasar($request, $tglDari, $tglSampai)
            ->select('d.id_detail', 't.id_transaksi', 't.nomor_nota', 't.tgl_order', 'k.nama_konsumen', 'd.nama_file', 'd.biaya_desain', 'd.komisi_desain_persen', 'd.komisi_desain',
                'd.komisi_status', 'd.komisi_dibayar_at', 'd.id_desainer', DB::raw('COALESCE(kw.nama_karyawan, u.username) as nama_desainer'))
            ->orderByDesc('t.tgl_order')->orderByDesc('d.id_detail')->paginate(25)->withQueryString();

        $desainerList = DesainKomisiService::daftarDesainer();
        $kelasList = DB::table('kelas_desainer')->orderBy('nama_kelas')->get();

        return view('laporan.biaya-desain', compact('tglDari', 'tglSampai', 'rekap', 'total', 'data', 'desainerList', 'kelasList', 'milikSendiri'));
    }

    public function bayar(Request $request)
    {
        if ($this->hanyaMilikSendiri()) {
            abort(403);
        }
        if (! \App\Support\Izin::bolehKeluarkanKasKecil($request->user())) {
            abort(403, 'Membayar komisi mengeluarkan uang Kas Kecil, jadi butuh hak tambah di menu Kas Kecil.');
        }
        $data = $request->validate(['id_desainer' => 'required|integer', 'tgl_dari' => 'required|date', 'tgl_sampai' => 'required|date|after_or_equal:tgl_dari']);

        try {
            $hasil = DesainKomisiService::bayar((int) $data['id_desainer'], $data['tgl_dari'], $data['tgl_sampai']);
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        }

        return back()->with('success', 'Komisi Rp ' . number_format($hasil['total'], 0, ',', '.') . " untuk {$hasil['item']} item dibayarkan dari Kas Kecil (KK-{$hasil['id_kas_kecil']}).");
    }

    // ---------------- Pengaturan kelas & bagi hasil ----------------

    public function pengaturan()
    {
        $kelas = DB::table('kelas_desainer as c')->leftJoin('users as u', 'u.id_kelas_desainer', '=', 'c.id_kelas')
            ->groupBy('c.id_kelas', 'c.nama_kelas', 'c.persen_komisi', 'c.keterangan')->orderBy('c.nama_kelas')
            ->selectRaw('c.id_kelas, c.nama_kelas, c.persen_komisi, c.keterangan, COUNT(u.id_user) as jumlah_anggota')->get();
        $anggota = DB::table('users as u')->join('roles as r', 'r.id_role', '=', 'u.id_role')->leftJoin('karyawan as kw', 'kw.id_karyawan', '=', 'u.id_karyawan')
            ->where('r.nama_role', DesainKomisiService::ROLE_DESAIN)->orderBy('u.username')
            ->get(['u.id_user', 'u.username', 'u.id_kelas_desainer', DB::raw('COALESCE(kw.nama_karyawan, u.username) as nama')]);

        return view('pengaturan.desainer', compact('kelas', 'anggota'));
    }

    public function simpanKelas(Request $request)
    {
        $data = $request->validate([
            'nama_kelas' => 'required|string|max:60|unique:kelas_desainer,nama_kelas',
            'persen_komisi' => 'required|numeric|min:0|max:100',
            'keterangan' => 'nullable|string|max:200',
        ], ['nama_kelas.unique' => 'Nama kelas sudah ada.']);
        DB::table('kelas_desainer')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Kelas desainer ditambahkan.');
    }

    public function updateKelas(Request $request, int $id)
    {
        $data = $request->validate([
            'nama_kelas' => 'required|string|max:60|unique:kelas_desainer,nama_kelas,' . $id . ',id_kelas',
            'persen_komisi' => 'required|numeric|min:0|max:100',
            'keterangan' => 'nullable|string|max:200',
        ], ['nama_kelas.unique' => 'Nama kelas sudah ada.']);
        DB::table('kelas_desainer')->where('id_kelas', $id)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Kelas desainer diperbarui. Nota yang sudah dibuat tetap memakai persen saat itu.');
    }

    public function hapusKelas(int $id)
    {
        if (DB::table('users')->where('id_kelas_desainer', $id)->exists()) {
            return back()->with('error', 'Kelas masih dipakai operator - pindahkan operatornya dulu.');
        }
        DB::table('kelas_desainer')->where('id_kelas', $id)->delete();

        return back()->with('success', 'Kelas desainer dihapus.');
    }

    public function tetapkanKelas(Request $request, int $idUser)
    {
        $data = $request->validate(['id_kelas' => 'nullable|integer|exists:kelas_desainer,id_kelas']);
        DB::table('users')->where('id_user', $idUser)->update(['id_kelas_desainer' => $data['id_kelas'] ?? null]);

        return back()->with('success', 'Kelas operator diperbarui. Berlaku untuk nota berikutnya.');
    }
}
