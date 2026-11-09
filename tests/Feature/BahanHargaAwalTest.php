<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BahanHargaAwalTest extends TestCase
{
    use RefreshDatabase;

    private int $idMesin;
    private int $idKategori;
    private int $idSatuan;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));

        $this->idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Digital']);
        $this->idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Uji']);
        $this->idSatuan = DB::table('satuan')->insertGetId(['nama_satuan' => 'Meter', 'nilai_konversi' => 1]);
    }

    private function registrasi(array $ekstra = [])
    {
        return $this->post('/bahan-baku', $ekstra + [
            'nama_bahan' => 'Kain Uji', 'kode_bahan' => 'KU-' . uniqid(), 'id_mesin' => $this->idMesin, 'id_kategori' => $this->idKategori,
            'id_satuan_stok' => $this->idSatuan, 'id_satuan_hitung' => $this->idSatuan, 'nilai_konversi' => 1,
            'stok' => 10, 'stok_minimal' => 2,
        ]);
    }

    private function saldo(string $akun): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $akun)->sum(DB::raw('debet - kredit'));
    }

    public function test_registrasi_dengan_harga_menetapkan_harga_rata_rata_dan_menjurnal_saldo_awal_persediaan(): void
    {
        $this->registrasi(['harga_beli' => 25000])->assertSessionHas('success');

        $bahan = DB::table('bahan_baku')->where('nama_bahan', 'Kain Uji')->first();
        $this->assertEquals(25000, (float) $bahan->harga_rata_rata);
        $this->assertEquals(10, (float) $bahan->stok);
        $this->assertEquals(250000, $this->saldo('1200'));
        $this->assertEquals(-250000, $this->saldo('3000'));
        $this->assertEquals(1, DB::table('kartu_stok')->where('id_bahan', $bahan->id_bahan)->where('referensi', 'STOK-AWAL')->count());
        $this->assertEquals(1, DB::table('audit_logs')->where('auditable_type', 'bahan_baku')->where('auditable_id', $bahan->id_bahan)->count());
    }

    public function test_catat_jurnal_dimatikan_tetap_menyimpan_harga_tanpa_jurnal(): void
    {
        $this->registrasi(['harga_beli' => 25000, 'catat_jurnal' => '0'])->assertSessionHas('success');

        $this->assertEquals(25000, (float) DB::table('bahan_baku')->where('nama_bahan', 'Kain Uji')->value('harga_rata_rata'));
        $this->assertEquals(0, DB::table('jurnal_umum')->count());
    }

    public function test_registrasi_tanpa_harga_seperti_biasa_tanpa_jurnal(): void
    {
        $this->registrasi()->assertSessionHas('success');

        $this->assertEquals(0, (float) DB::table('bahan_baku')->where('nama_bahan', 'Kain Uji')->value('harga_rata_rata'));
        $this->assertEquals(0, DB::table('jurnal_umum')->count());
    }

    public function test_koreksi_harga_wajib_alasan_tercatat_dan_tidak_mengubah_stok_atau_jurnal(): void
    {
        $this->registrasi(['stok' => 7, 'catat_jurnal' => '0'])->assertSessionHas('success');
        $id = (int) DB::table('bahan_baku')->where('nama_bahan', 'Kain Uji')->value('id_bahan');

        $this->patch("/bahan-baku/{$id}/harga", ['harga_rata_rata' => 30000])->assertSessionHasErrors('alasan');
        $this->assertEquals(0, (float) DB::table('bahan_baku')->where('id_bahan', $id)->value('harga_rata_rata'));

        $this->patch("/bahan-baku/{$id}/harga", ['harga_rata_rata' => 30000, 'alasan' => 'Isi harga awal'])->assertSessionHas('success');

        $bahan = DB::table('bahan_baku')->where('id_bahan', $id)->first();
        $this->assertEquals(30000, (float) $bahan->harga_rata_rata);
        $this->assertEquals(7, (float) $bahan->stok);
        $this->assertEquals(0, DB::table('jurnal_umum')->count());
        $log = DB::table('audit_logs')->where('auditable_type', 'bahan_baku')->where('auditable_id', $id)->latest('id')->first();
        $this->assertStringContainsString('Isi harga awal', $log->keterangan);

        $this->patch("/bahan-baku/{$id}/harga", ['harga_rata_rata' => 30000, 'alasan' => 'sama'])->assertSessionHas('error');
        $this->patch("/bahan-baku/{$id}/harga", ['harga_rata_rata' => -1, 'alasan' => 'x'])->assertSessionHasErrors('harga_rata_rata');
    }

    public function test_daftar_bahan_menampilkan_penanda_dan_filter_belum_ada_harga(): void
    {
        $this->registrasi(['nama_bahan' => 'Tanpa Harga', 'kode_bahan' => 'TH-1'])->assertSessionHas('success');
        $this->registrasi(['nama_bahan' => 'Berharga', 'kode_bahan' => 'BH-1', 'harga_beli' => 5000, 'catat_jurnal' => '0'])->assertSessionHas('success');

        $this->get('/bahan-baku')->assertOk()->assertSee('Belum ada harga')->assertSee('Tanpa Harga')->assertSee('Berharga');
        $this->get('/bahan-baku?harga=kosong')->assertOk()->assertSee('Tanpa Harga')->assertDontSee('Berharga');
    }

    public function test_urutan_bawaan_daftar_bahan_adalah_abjad_dan_pilihan_abjad_terpilih(): void
    {
        foreach ([["Zeta", "Z-1"], ["Alfa", "A-1"], ["Metro", "M-1"]] as [$nama, $kode]) {
            $this->registrasi(["nama_bahan" => $nama, "kode_bahan" => $kode])->assertSessionHas("success");
        }

        $res = $this->get("/bahan-baku")->assertOk();
        $urut = $res->viewData("bahan")->pluck("nama_bahan")->all();
        $this->assertSame(["Alfa", "Metro", "Zeta"], $urut);
        $res->assertSee('value="nama_bahan" selected', false);

        $this->get("/bahan-baku?sort=stok")->assertOk();
    }

    public function test_dropdown_filter_form_get_otomatis_menerapkan_filter(): void
    {
        $this->get("/dashboard")->assertOk()->assertSee("form.__terkirim", false);
    }
}
