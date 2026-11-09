<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Isian yang lebih panjang dari kolomnya ditolak validasi (pesan jelas), bukan galat database saat menyimpan. */
class PanjangIsianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
    }

    public function test_nama_terlalu_panjang_ditolak_validasi(): void
    {
        $this->post('/kategori', ['nama_kategori' => str_repeat('K', 51)])->assertSessionHasErrors('nama_kategori');
        $this->post('/golongan', ['nama_golongan' => str_repeat('G', 51)])->assertSessionHasErrors('nama_golongan');
        $this->post('/bank', ['nama_bank' => str_repeat('B', 51), 'nomor_rekening' => '1', 'atas_nama' => 'A'])->assertSessionHasErrors('nama_bank');
        $this->post('/supplier', ['nama_supplier' => 'S', 'kontak' => str_repeat('9', 256)])->assertSessionHasErrors('kontak');

        $this->assertSame(0, DB::table('kategori')->count());
        $this->assertSame(0, DB::table('bank_perusahaan')->count());
    }

    public function test_batas_yang_pas_tetap_diterima(): void
    {
        $this->post('/kategori', ['nama_kategori' => str_repeat('K', 50), 'kode_kategori' => 'KTG'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('kategori')->count());
    }

    public function test_tanggal_dokumen_uang_dan_stok_tidak_boleh_di_masa_depan(): void
    {
        $besok = now()->addDay()->toDateString();

        $this->post('/jersey/store', ['order_date' => $besok])->assertSessionHasErrors('order_date');
        $this->post('/stock-opname', ['tanggal' => $besok])->assertSessionHasErrors('tanggal');
        $this->post('/purchase-order', ['tanggal' => $besok])->assertSessionHasErrors('tanggal');
    }

    public function test_pengeluaran_kas_kecil_melebihi_saldo_ditolak(): void
    {
        \App\Services\AccountingService::postKasKecilMasuk('KK-SALDO', '3000', 50000);
        $kat = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'Uji Beban', 'jenis' => 'Keluar', 'kode_akun' => '5100']);
        $data = ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $kat, 'keterangan' => 'Beli lakban'];

        $this->post('/kas-kecil', $data + ['nominal' => 60000])->assertSessionHas('error');
        $this->assertSame(0, DB::table('kas_kecil')->count());
        $this->post('/kas-kecil', $data + ['nominal' => 50000])->assertSessionHas('success');
    }
}
