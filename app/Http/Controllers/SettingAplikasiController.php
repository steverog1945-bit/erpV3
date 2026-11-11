<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingAplikasiController extends Controller
{
    public function __construct()
    {
        // Gembok kedua di atas middleware hanya.admin & khusus_administrator
        // (config/menu_access.php): halaman ini mengatur identitas toko,
        // logo, format nota & pembulatan - hanya Administrator (exact).
        abort_unless(\App\Support\Izin::adalahAdmin(request()->user()), 403, 'Halaman ini khusus Administrator.');
    }

    public function edit()
    {
        $setting = AppSetting::current();
        return view('pengaturan.aplikasi', compact('setting'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nama_aplikasi' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:1000',
            'telepon' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'url_akses' => ['nullable', 'string', 'max:255', 'regex:#^https?://[^\s]+$#i'],
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'logo_ikon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024',
            'hapus_logo_ikon' => 'nullable|boolean',
            'logo_ukuran' => 'nullable|in:kecil,sedang,besar',
            'logo_gaya' => 'nullable|in:polos,kaca,putih',
            'logo_ringkas' => 'nullable|in:potong,penuh',
            'libur_kas_minggu' => 'nullable|boolean',
            'batasi_jam_kerja' => 'nullable|boolean',
            'approval_edit_nota' => 'nullable|boolean',
            'format_nota' => 'nullable|in:thermal,a4,continuous',
            'nomor_nota_prefix' => 'nullable|string|max:20|regex:/^[A-Za-z0-9\-]+$/',
            'nota_footer_text' => 'nullable|string|max:5000',
            'pembulatan_nota' => 'nullable|in:tidak,100,500,1000',
        ]);

        $setting = AppSetting::current();

        $updateData = [
            'nama_aplikasi' => $data['nama_aplikasi'],
            'alamat' => $data['alamat'] ?? null,
            'telepon' => $data['telepon'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'url_akses' => isset($data['url_akses']) ? rtrim($data['url_akses'], '/') : null,
            'libur_kas_minggu' => $request->boolean('libur_kas_minggu'),
            'batasi_jam_kerja' => $request->boolean('batasi_jam_kerja'),
            'approval_edit_nota' => $request->boolean('approval_edit_nota'),
            'format_nota' => $data['format_nota'] ?? 'a4',
            'nomor_nota_prefix' => $data['nomor_nota_prefix'] ?? 'INV',
            'nota_footer_text' => $this->sanitizeFooterHtml($data['nota_footer_text'] ?? null),
            'pembulatan_nota' => $data['pembulatan_nota'] ?? 'tidak',
            'logo_ukuran' => $data['logo_ukuran'] ?? $setting->logo_ukuran ?? 'sedang',
            'logo_gaya' => $data['logo_gaya'] ?? $setting->logo_gaya ?? 'polos',
            'logo_ringkas' => $data['logo_ringkas'] ?? $setting->logo_ringkas ?? 'potong',
        ];

        if ($request->hasFile('logo')) {
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $updateData['logo_path'] = $request->file('logo')->store('company_logo', 'public');
        }

        if ($request->hasFile('logo_ikon') || $request->boolean('hapus_logo_ikon')) {
            if ($setting->logo_ikon_path && Storage::disk('public')->exists($setting->logo_ikon_path)) {
                Storage::disk('public')->delete($setting->logo_ikon_path);
            }
            $updateData['logo_ikon_path'] = $request->hasFile('logo_ikon') ? $request->file('logo_ikon')->store('company_logo', 'public') : null;
        }

        $setting->update($updateData);
        AppSetting::clearCache();

        return back()->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    /**
     * Preview LIVE nota (dipakai lewat <iframe> di tab Nota Pengaturan
     * Aplikasi) - merender view kasir.print_nota YANG SAMA PERSIS dengan
     * yang dipakai cetak sungguhan (bukan mockup terpisah yang harus
     * dijaga manual biar tetap sinkron - WYSIWYG asli, tidak mungkin
     * "lupa" diperbarui kalau print_nota diubah lagi nanti).
     *
     * format/prefix/footer diambil dari QUERY STRING (dikirim JS setiap
     * field di form Nota berubah) supaya preview reflect perubahan yang
     * BELUM disimpan - fallback ke AppSetting::current() kalau query
     * kosong (preview awal sebelum ada perubahan apa pun). Footer dari
     * query tetap disaring lewat sanitizeFooterHtml() yang sama seperti
     * saat disimpan sungguhan - endpoint ini publik-ke-user-login, bukan
     * alasan buat melewati sanitasi.
     *
     * Data transaksi & item SELALU contoh statis (bukan dari database) -
     * endpoint pratinjau tidak boleh membocorkan data transaksi/konsumen
     * asli, dan supaya preview selalu konsisten apa pun isi database saat
     * ini. Satu nama item sengaja dibuat sangat panjang untuk
     * memperlihatkan potongan ellipsis-nya juga.
     */
    public function previewNota(Request $request)
    {
        $setting = AppSetting::current();

        $format = $request->query('format');
        $format = in_array($format, ['thermal', 'a4', 'continuous'], true) ? $format : ($setting->format_nota ?? 'a4');

        $prefix = $request->query('prefix');
        $prefix = ($prefix && preg_match('/^[A-Za-z0-9\-]+$/', $prefix)) ? $prefix : ($setting->nomor_nota_prefix ?? 'INV');

        $footerQuery = $request->query('footer');
        $footerNota = $footerQuery !== null ? $this->sanitizeFooterHtml($footerQuery) : $setting->nota_footer_text;

        $pembulatanQuery = $request->query('pembulatan');
        $pembulatanQuery = in_array($pembulatanQuery, ['tidak', '100', '500', '1000'], true)
            ? $pembulatanQuery
            : ($setting->pembulatan_nota ?? 'tidak');

        // Grand Total contoh SENGAJA nominal ganjil (200.300, bukan bulat
        // 200.000) - supaya baris "Pembulatan" langsung kelihatan di
        // preview ini begitu admin mengaktifkan/mengganti kelipatan
        // pembulatan, tanpa perlu bikin transaksi asli dulu untuk mengecek.
        $totalTagihanAsli = 200300.0;
        $totalTagihanBulat = AppSetting::bulatkan($totalTagihanAsli, $pembulatanQuery);
        // Contoh sengaja menunjukkan pembayaran dua kali: DP 100.000 Tunai lalu
        // pelunasan 100.300 Transfer. Gunanya supaya preview ini sekaligus
        // membuktikan blok "Status Bayar / Metode Bayar / Uang Diterima" ikut
        // berubah kalau nota asli berubah - bukan hanya tampil di halaman kasir.
        $penerimaanNota = collect([
            (object) ['nominal' => 100000.0, 'metode' => 'Tunai', 'tgl_bayar' => now()->setTime(10, 15), 'kembalian' => 0],
            (object) ['nominal' => 100300.0, 'metode' => 'Transfer', 'tgl_bayar' => now()->setTime(14, 40), 'kembalian' => 0],
        ]);
        $totalTerbayar = (float) $penerimaanNota->sum('nominal');

        $transaksi = (object) [
            'nomor_nota' => $prefix . '-' . now()->format('Ymd') . '-001',
            'tgl_order' => now(),
            'nama_konsumen' => 'Budi Santoso (Contoh)',
            'telepon' => '0812xxxxxxx',
            'nama_kasir' => 'Contoh Kasir',
            'total_bayar' => $totalTagihanAsli,
            'status_pembayaran' => 'Lunas',
            'id_konsumen' => 0,
        ];
        $items = collect([
            // Produk ber-metode 'ukuran' (roll/banner): ukuran TIDAK ikut
            // tercetak di nota, jadi contoh ini memperlihatkan efeknya.
            (object) ['nama_file' => 'Banner Outdoor 3x1m Custom Full Desain Logo Perusahaan', 'qty' => 2, 'subtotal' => 150300, 'biaya_cetak' => 150300, 'biaya_desain' => 0, 'biaya_finishing' => 0, 'p_ukuran' => 300, 'l_ukuran' => 100, 'metode_hitung_produk' => 'ukuran'],
            // Produk per lembar (A3): tidak punya ukuran di form.
            (object) ['nama_file' => 'Stiker A3', 'qty' => 5, 'subtotal' => 50000, 'biaya_cetak' => 40000, 'biaya_desain' => 0, 'biaya_finishing' => 10000, 'p_ukuran' => 0, 'l_ukuran' => 0, 'metode_hitung_produk' => 'quantity'],
        ]);

        return view('kasir.print_nota', [
            'transaksi' => $transaksi,
            'items' => $items,
            'totalTerbayar' => $totalTerbayar,
            'format' => $format,
            'totalCetak' => 190300,
            'totalDesain' => 0,
            'totalFinishing' => 10000,
            'kembali' => max(0, $totalTerbayar - $totalTagihanBulat),
            'kurangBayar' => max(0, $totalTagihanBulat - $totalTerbayar),
            'totalTagihanAsli' => $totalTagihanAsli,
            'totalTagihanBulat' => $totalTagihanBulat,
            'pembulatan' => $totalTagihanBulat - $totalTagihanAsli,
            'depositTerpakaiTransaksiIni' => 0,
            'saldoDepositSaatIni' => 0,
            'konsumenPernahPakaiDeposit' => false,
            'penerimaanNota' => $penerimaanNota,
            'statusPembayaranNota' => \App\Services\StatusPembayaran::dari($totalTagihanBulat, $totalTerbayar),
            'metodePembayaranNota' => $penerimaanNota->pluck('metode')->unique()->implode(', '),
            'footerNotaOverride' => $footerNota,
        ]);
    }

    /**
     * Footer nota diketik lewat editor contenteditable (toolbar tebal/
     * miring/garis-bawah/daftar) di sisi klien, hasilnya HTML mentah
     * (element.innerHTML) - WAJIB disaring di server sebelum disimpan,
     * karena nilai ini nantinya dicetak balik sebagai HTML MENTAH
     * (bukan di-escape) di kasir.print_nota. Dua lapis:
     * 1. strip_tags dengan whitelist SANGAT ketat (cuma tag pembentuk
     *    format dasar, tidak ada <a>/<img>/<script>/dst).
     * 2. Buang SEMUA atribut dari tag yang tersisa (termasuk onclick=,
     *    style=, href=javascript:) - formatting dasar tidak butuh atribut
     *    apa pun, jadi paling aman dihapus total daripada whitelist per-
     *    atribut yang gampang bocor.
     */
    private function sanitizeFooterHtml(?string $html): ?string
    {
        if (!$html || trim(strip_tags($html)) === '') {
            return null;
        }

        $allowedTags = '<b><strong><i><em><u><br><p><div><ul><ol><li>';
        $bersih = strip_tags($html, $allowedTags);
        $bersih = preg_replace('/<([a-z][a-z0-9]*)[^>]*>/i', '<$1>', $bersih);

        return trim($bersih);
    }
}
