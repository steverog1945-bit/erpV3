<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BEHAVIORAL AUDIT — Pattern A: form native (submit browser → 302 redirect-back).
 *
 * Pertanyaan audit (per endpoint):
 *   1. server menolak isian tidak valid?  -> respons 302 + errors di session
 *   2. tidak ada record baru?             -> count tabel sebelum == sesudah
 *   3. pesan penolakan TERLIHAT?          -> HTML halaman tujuan redirect-back
 *      memuat popup "Periksa kembali isian" (layouts/app.blade.php)
 *      DAN teks pesan validasinya.
 *
 * Catatan pesan: Laravel memakai :attribute dengan spasi
 * (nama_supplier -> "nama supplier"), jadi teks asersi memakai
 * spasi, bukan garis bawah.
 *
 * Dua request per baris (sengaja):
 *   - request 1 TANPA follow: membuktikan penolakan server (302 + session errors)
 *   - request 2 DENGAN follow: membuktikan pesan terlihat di halaman tujuan
 *
 * 'dari' = halaman form (referer) — harus halaman GET, karena back()
 * mengarah ke sana dan response di-follow dengan GET.
 *
 * Fixture: role Administrator (selalu lolos CheckMenuAccess &
 * AksesHarga::bolehLihat). Database testing (RefreshDatabase).
 */
class UmpanBalikFormNativeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $idRole = (int) DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $idRole]);
    }

    /** @dataProvider penyediaBuat */
    public function test_buat_ditolak_dan_pesan_terlihat(
        string $uri,
        string $dari,
        array $payload,
        string $field,
        string $pesan,
        string $table,
        string $method = 'post'
    ): void {
        $sebelum = DB::table($table)->count();

        // (1) server menolak: 302 ke halaman form + error di session
        $r = $this->actingAs($this->admin)
            ->from($dari)
            ->$method($uri, $payload);

        $r->assertStatus(302);
        $r->assertSessionHasErrors([$field]);

        // (2) pesan terlihat di halaman tujuan redirect-back
        $r2 = $this->actingAs($this->admin)
            ->from($dari)
            ->followingRedirects()
            ->$method($uri, $payload);

        $html = (string) $r2->getContent();
        $this->assertStringContainsString('Periksa kembali isian', $html);
        $this->assertStringContainsString($pesan, $html);

        // (3) DB safety: tidak ada record baru
        $this->assertSame($sebelum, DB::table($table)->count(), "record baru muncul di {$table}");
    }

    public static function penyediaBuat(): array
    {
        return [
            // ---- master data (form modal di halaman indeks = URI aksi) ----
            'supplier' => [
                'uri' => '/supplier', 'dari' => '/supplier',
                'payload' => ['nama_supplier' => '', 'kontak' => ''],
                'field' => 'nama_supplier', 'pesan' => 'Nama supplier wajib diisi.',
                'table' => 'supplier',
            ],
            'bank' => [
                'uri' => '/bank', 'dari' => '/bank',
                'payload' => ['nama_bank' => '', 'nomor_rekening' => '', 'atas_nama' => ''],
                'field' => 'nama_bank', 'pesan' => 'Nama bank wajib diisi.',
                'table' => 'bank_perusahaan',
            ],
            'golongan' => [
                'uri' => '/golongan', 'dari' => '/golongan',
                'payload' => ['nama_golongan' => ''],
                'field' => 'nama_golongan', 'pesan' => 'Nama golongan wajib diisi.',
                'table' => 'golongan_konsumen',
            ],
            'gudang' => [
                'uri' => '/gudang', 'dari' => '/gudang',
                'payload' => ['nama_gudang' => ''],
                'field' => 'nama_gudang', 'pesan' => 'Nama gudang wajib diisi.',
                'table' => 'gudang',
            ],
            'merk' => [
                'uri' => '/merk', 'dari' => '/merk',
                'payload' => ['nama_merk' => ''],
                'field' => 'nama_merk', 'pesan' => 'Nama merk wajib diisi.',
                'table' => 'merk',
            ],
            'kategori' => [
                'uri' => '/kategori', 'dari' => '/kategori',
                'payload' => ['nama_kategori' => '', 'kode_kategori' => ''],
                'field' => 'nama_kategori', 'pesan' => 'Nama kategori wajib diisi.',
                'table' => 'kategori',
            ],
            'jenis-bahan' => [
                'uri' => '/jenis-bahan', 'dari' => '/jenis-bahan',
                'payload' => ['nama_jenis' => ''],
                'field' => 'nama_jenis', 'pesan' => 'Nama jenis wajib diisi.',
                'table' => 'jenis_bahan',
            ],
            'satuan' => [
                'uri' => '/satuan', 'dari' => '/satuan',
                'payload' => ['nama_satuan' => '', 'nilai_konversi' => ''],
                'field' => 'nama_satuan', 'pesan' => 'Nama satuan wajib diisi.',
                'table' => 'satuan',
            ],
            'karyawan' => [
                'uri' => '/karyawan/store', 'dari' => '/karyawan',
                'payload' => ['nama_karyawan' => '', 'bagian' => '', 'telepon' => ''],
                'field' => 'nama_karyawan', 'pesan' => 'Nama karyawan wajib diisi.',
                'table' => 'karyawan',
            ],
            'mesin' => [
                'uri' => '/mesin', 'dari' => '/mesin',
                'payload' => ['nama_mesin' => '', 'tipe_mesin' => ''],
                'field' => 'nama_mesin', 'pesan' => 'Nama mesin wajib diisi.',
                'table' => 'mesin',
            ],
            'produk' => [
                'uri' => '/produk', 'dari' => '/produk',
                'payload' => ['nama_produk' => '', 'metode_hitung' => ''],
                'field' => 'nama_produk', 'pesan' => 'Nama produk wajib diisi.',
                'table' => 'produk',
            ],
            'akun' => [
                'uri' => '/akun', 'dari' => '/akun',
                'payload' => ['kode_akun' => '', 'nama_akun' => '', 'tipe_akun' => '', 'saldo_normal' => ''],
                'field' => 'kode_akun', 'pesan' => 'Kode akun wajib diisi.',
                'table' => 'bag_akun',
            ],
            'harga-promo' => [
                'uri' => '/harga-promo', 'dari' => '/harga-promo',
                'payload' => ['id_produk' => '', 'harga_promo' => '', 'tgl_mulai' => '', 'tgl_selesai' => ''],
                'field' => 'id_produk', 'pesan' => 'Id produk wajib diisi.',
                'table' => 'harga_promo',
            ],
            'kategori-kas-kecil' => [
                'uri' => '/kategori-kas-kecil', 'dari' => '/kategori-kas-kecil',
                'payload' => ['nama_kategori' => '', 'jenis' => ''],
                'field' => 'nama_kategori', 'pesan' => 'Nama kategori wajib diisi.',
                'table' => 'kategori_kas_kecil',
            ],
            'platform-belanja' => [
                'uri' => '/platform-belanja', 'dari' => '/platform-belanja',
                'payload' => ['nama' => ''],
                'field' => 'nama', 'pesan' => 'Nama wajib diisi.',
                'table' => 'platform_belanja',
            ],
            'menu-kustom' => [
                'uri' => '/menu-editor/tambah', 'dari' => '/menu-editor',
                'payload' => ['label' => '', 'url' => ''],
                'field' => 'label', 'pesan' => 'Label wajib diisi.',
                'table' => 'menus',
            ],
            'kelas-desainer' => [
                'uri' => '/pengaturan-desainer/kelas', 'dari' => '/pengaturan-desainer',
                'payload' => ['nama_kelas' => '', 'persen_komisi' => ''],
                'field' => 'nama_kelas', 'pesan' => 'Nama kelas wajib diisi.',
                'table' => 'kelas_desainer',
            ],
            'jasa' => [
                'uri' => '/jasa', 'dari' => '/jasa',
                'payload' => ['nama_jasa' => ''],
                'field' => 'nama_jasa', 'pesan' => 'Nama jasa wajib diisi.',
                'table' => 'jasa',
            ],
            'bahan-baku' => [
                'uri' => '/bahan-baku', 'dari' => '/bahan-baku',
                'payload' => ['nama_bahan' => ''],
                'field' => 'nama_bahan', 'pesan' => 'Nama bahan wajib diisi.',
                'table' => 'bahan_baku',
            ],
            'konsumen-baru' => [
                'uri' => '/konsumen', 'dari' => '/konsumen',
                'payload' => ['nama_konsumen' => ''],
                'field' => 'nama_konsumen', 'pesan' => 'Nama konsumen wajib diisi.',
                'table' => 'konsumen',
            ],

            // ---- operasional (form di halaman lain dari URI aksi) ----
            'finishing' => [
                'uri' => '/finishing', 'dari' => '/finishing',
                'payload' => ['nama_finishing' => '', 'harga_default' => '', 'metode_hitung' => ''],
                'field' => 'nama_finishing', 'pesan' => 'Nama finishing wajib diisi.',
                'table' => 'finishing',
            ],
            'jasa-jahit' => [
                'uri' => '/jasa-jahit', 'dari' => '/jasa-jahit',
                'payload' => ['nama_jahit' => '', 'harga_borongan' => ''],
                'field' => 'nama_jahit', 'pesan' => 'Nama jahit wajib diisi.',
                'table' => 'jasa_jahit',
            ],
            'penjahit' => [
                'uri' => '/jasa-jahit/penjahit', 'dari' => '/jasa-jahit',
                'payload' => ['nama' => ''],
                'field' => 'nama', 'pesan' => 'Nama wajib diisi.',
                'table' => 'penjahit',
            ],
            'catat-jahit' => [
                'uri' => '/jasa-jahit/catat', 'dari' => '/jasa-jahit',
                'payload' => ['id_jasa_jahit' => ''],
                'field' => 'id_jasa_jahit', 'pesan' => 'Id jasa jahit wajib diisi.',
                'table' => 'jasa_jahit_catat',
            ],
            'bayar-penjahit' => [
                'uri' => '/jasa-jahit/bayar', 'dari' => '/jasa-jahit',
                'payload' => ['id_penjahit' => ''],
                'field' => 'id_penjahit', 'pesan' => 'Id penjahit wajib diisi.',
                'table' => 'pembayaran_riwayat',
            ],
            'kartu-stok-adjustment' => [
                'uri' => '/kartu-stok/adjustment', 'dari' => '/kartu-stok',
                'payload' => ['id_bahan' => ''],
                'field' => 'id_bahan', 'pesan' => 'Id bahan wajib diisi.',
                'table' => 'kartu_stok',
            ],
            'kas-kecil' => [
                'uri' => '/kas-kecil', 'dari' => '/kas-kecil',
                'payload' => ['tanggal' => ''],
                'field' => 'tanggal', 'pesan' => 'Tanggal wajib diisi.',
                'table' => 'kas_kecil',
            ],
            'saldo-awal' => [
                'uri' => '/kas-kecil/saldo-awal', 'dari' => '/kas-kecil',
                'payload' => ['tanggal' => ''],
                'field' => 'tanggal', 'pesan' => 'Tanggal wajib diisi.',
                'table' => 'kas_kecil',
            ],
            'kas-manual' => [
                'uri' => '/kasir/kas-manual', 'dari' => '/kasir/kelola-kas',
                'payload' => ['keterangan' => ''],
                'field' => 'keterangan', 'pesan' => 'Keterangan wajib diisi.',
                'table' => 'kas_manual',
            ],
            'konsumen-deposit' => [
                'uri' => '/konsumen-deposit', 'dari' => '/konsumen-deposit',
                'payload' => ['id_konsumen' => ''],
                'field' => 'id_konsumen', 'pesan' => 'Id konsumen wajib diisi.',
                'table' => 'konsumen_deposit',
            ],
            'tutup-periode' => [
                'uri' => '/accounting-period/tutup', 'dari' => '/accounting-period', 'method' => 'patch',
                'payload' => ['tahun' => ''],
                'field' => 'tahun', 'pesan' => 'Tahun wajib diisi.',
                'table' => 'accounting_periods',
            ],
            'buka-periode' => [
                'uri' => '/accounting-period/buka', 'dari' => '/accounting-period', 'method' => 'patch',
                'payload' => ['tahun' => ''],
                'field' => 'tahun', 'pesan' => 'Tahun wajib diisi.',
                'table' => 'accounting_periods',
            ],
            'pemakaian-tinta' => [
                'uri' => '/laporan/biaya-tinta/pengaturan', 'dari' => '/laporan/biaya-tinta',
                'payload' => ['id_mesin' => ''],
                'field' => 'id_mesin', 'pesan' => 'Id mesin wajib diisi.',
                'table' => 'pemakaian_tinta',
            ],
            'komisi-desain-bayar' => [
                'uri' => '/laporan/biaya-desain/bayar', 'dari' => '/laporan/biaya-desain',
                'payload' => ['id_desainer' => ''],
                'field' => 'id_desainer', 'pesan' => 'Id desainer wajib diisi.',
                'table' => 'pembayaran_riwayat',
            ],
            'waste' => [
                'uri' => '/waste', 'dari' => '/waste/create',
                'payload' => ['id_bahan' => ''],
                'field' => 'id_bahan', 'pesan' => 'Id bahan wajib diisi.',
                'table' => 'waste_log',
            ],
            'qc' => [
                'uri' => '/qc', 'dari' => '/qc/create',
                'payload' => ['id_detail' => ''],
                'field' => 'id_detail', 'pesan' => 'Id detail wajib diisi.',
                'table' => 'qc_inspeksi',
            ],
            'stock-opname' => [
                'uri' => '/stock-opname', 'dari' => '/stock-opname/create',
                'payload' => ['tanggal' => ''],
                'field' => 'tanggal', 'pesan' => 'Tanggal wajib diisi.',
                'table' => 'stock_opnames',
            ],
            'pembelian-bahan' => [
                'uri' => '/pembelian-bahan', 'dari' => '/pembelian-bahan/create',
                'payload' => ['no_faktur' => ''],
                'field' => 'no_faktur', 'pesan' => 'No faktur wajib diisi.',
                'table' => 'pembelian_bahan',
            ],
            'purchase-order' => [
                'uri' => '/purchase-order', 'dari' => '/purchase-order/create',
                'payload' => ['id_supplier' => ''],
                'field' => 'id_supplier', 'pesan' => 'Supplier wajib dipilih.',
                'table' => 'purchase_orders',
            ],
            'purchase-request' => [
                'uri' => '/purchase-request', 'dari' => '/purchase-request/create',
                'payload' => ['tanggal' => ''],
                'field' => 'tanggal', 'pesan' => 'Tanggal wajib diisi.',
                'table' => 'purchase_requests',
            ],
            'quotation' => [
                'uri' => '/quotation', 'dari' => '/quotation/create',
                'payload' => ['tanggal' => ''],
                'field' => 'tanggal', 'pesan' => 'Tanggal wajib diisi.',
                'table' => 'quotations',
            ],
            'penjualan-barang' => [
                'uri' => '/penjualan-barang/store', 'dari' => '/penjualan-barang',
                'payload' => ['id_konsumen' => ''],
                'field' => 'id_konsumen', 'pesan' => 'Id konsumen wajib diisi.',
                'table' => 'transaksi',
            ],
            'postingan' => [
                'uri' => '/postingan', 'dari' => '/postingan/create',
                'payload' => ['judul' => ''],
                'field' => 'judul', 'pesan' => 'Judul wajib diisi.',
                'table' => 'posts',
            ],
            'order-jersey' => [
                'uri' => '/jersey/store', 'dari' => '/jersey/create',
                'payload' => ['order_date' => ''],
                'field' => 'order_date', 'pesan' => 'Order date wajib diisi.',
                'table' => 'jersey_orders',
            ],
            'kedatangan-bahan' => [
                'uri' => '/kedatangan-bahan', 'dari' => '/kedatangan-bahan/create',
                'payload' => ['no_surat_jalan' => ''],
                'field' => 'no_surat_jalan', 'pesan' => 'No surat jalan wajib diisi.',
                'table' => 'kedatangan_bahan',
            ],
        ];
    }
}
