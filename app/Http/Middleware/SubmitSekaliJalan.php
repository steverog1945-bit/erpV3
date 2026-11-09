<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjamin satu pengiriman (satu `submit_id`) hanya diproses SEKALI oleh server. Terpasang di grup web untuk semua
 * permintaan tulis; layout mengisi `submit_id` otomatis pada form yang dikirim langsung oleh browser.
 *
 * Kenapa perlu, kalau tombol sudah dikunci di browser: kunci itu hanya hidup di satu tab. Bila jaringan putus
 * SETELAH server menyimpan, layar menampilkan "gagal", operator menekan simpan lagi, dan lahir transaksi kedua.
 * Dua tab atau dua perangkat yang mengirim isi yang sama juga lolos dari kunci browser. Aturannya:
 *
 * - `submit_id` kosong / metode GET: dibiarkan lewat (kompatibel dengan klien dan form lama).
 * - Pengiriman pertama mengunci id itu (Cache::add atomik) lalu berjalan.
 * - Bila selesai sukses, hasilnya disimpan: data JSON, atau tujuan redirect + pesan sukses untuk form biasa.
 *   Pengiriman ulang dengan id yang sama TIDAK menjalankan apa pun lagi; ia menerima hasil yang sama.
 * - Bila gagal (validasi, stok, galat), kunci dilepas supaya operator bisa memperbaiki data lalu mengirim lagi.
 * - Bila kunci masih terpegang tanpa hasil (pengiriman pertama masih jalan), pengiriman kedua ditolak, bukan
 *   diproses paralel.
 */
class SubmitSekaliJalan
{
    /** Hasil sukses diingat selama ini untuk menjawab kiriman ulang. */
    private const TTL_HASIL_MENIT = 15;

    /**
     * Kunci "sedang diproses" sengaja pendek (sedikit di atas max_execution_time 120 detik): bila proses mati mendadak
     * sehingga kunci tidak pernah dilepas, operator hanya tertahan beberapa menit, bukan 15 menit. Tidak ada permintaan
     * web yang bisa berjalan lebih lama dari batas itu (tidak ada set_time_limit di aplikasi, dan di Windows batasnya
     * waktu nyata termasuk menunggu database), jadi kunci tidak kedaluwarsa selagi pengiriman pertama masih berjalan.
     */
    private const TTL_DIPROSES_MENIT = 3;

    public function handle(Request $request, Closure $next): Response
    {
        $id = trim((string) $request->input('submit_id'));
        if ($request->isMethodSafe() || $id === '' || strlen($id) > 100) {
            return $next($request);
        }

        // Sidik isi kiriman ikut menjadi kunci: submit_id yang sama dengan isi BERBEDA (form yang id-nya tidak berganti)
        // adalah kiriman baru dan diproses, bukan dijawab dengan hasil kiriman sebelumnya tanpa menyimpan apa pun.
        $isi = $request->except(['submit_id', '_token', '_method']);
        $sidik = md5(serialize($isi));
        $kunci = 'submit:' . optional($request->route())->getName() . ':' . $request->path() . ':' . Auth::id() . ':' . $id . ':' . $sidik;

        if (!Cache::add($kunci, 'diproses', now()->addMinutes(self::TTL_DIPROSES_MENIT))) {
            return $this->ulangan($request, Cache::get($kunci));
        }

        try {
            $respons = $next($request);
        } catch (\Throwable $e) {
            Cache::forget($kunci);
            throw $e;
        }

        $hasil = $this->hasilSukses($request, $respons);
        if ($hasil !== null) {
            Cache::put($kunci, $hasil, now()->addMinutes(self::TTL_HASIL_MENIT));
        } else {
            Cache::forget($kunci);
        }

        return $respons;
    }

    /** Hasil yang disimpan untuk dikembalikan ke pengiriman ulang, atau null bila pengiriman ini tidak sukses. */
    private function hasilSukses(Request $request, Response $respons): ?array
    {
        if ($respons instanceof JsonResponse) {
            $data = $respons->getData(true);

            // Dua bentuk yang dipakai aplikasi ini: {status: 'success'} dan {success: true} (master Jersey, harga).
            $sukses = is_array($data) && (($data['status'] ?? null) === 'success' || ($data['success'] ?? null) === true);

            return $respons->isSuccessful() && $sukses ? ['json' => $data] : null;
        }

        // Form biasa: sukses = redirect dengan pesan sukses yang di-flash OLEH PERMINTAAN INI, tanpa galat/validasi.
        // Pesan sukses dari permintaan sebelumnya yang belum sempat tampil masih ada di sesi dan tidak boleh dihitung.
        $sesi = $request->hasSession() ? $request->session() : null;
        $baru = $sesi ? (array) $sesi->get('_flash.new', []) : [];
        if ($respons->isRedirection() && in_array('success', $baru, true) && !in_array('error', $baru, true) && !in_array('errors', $baru, true)) {
            return ['redirect' => $respons->headers->get('Location'), 'success' => (string) $sesi->get('success')];
        }

        return null;
    }

    private function ulangan(Request $request, mixed $hasil): Response
    {
        if (is_array($hasil) && isset($hasil['json'])) {
            return response()->json($hasil['json'] + ['ulang' => true]);
        }
        if (is_array($hasil) && isset($hasil['redirect'])) {
            return redirect()->to($hasil['redirect'])->with('success', $hasil['success'] . ' (Kiriman ulang tidak dicatat dua kali.)');
        }

        $pesan = 'Pengiriman ini sedang diproses. Tunggu sebentar, lalu cek daftar sebelum mengirim ulang.';
        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'msg' => $pesan], 409);
        }

        return redirect()->back()->with('error', $pesan);
    }
}
