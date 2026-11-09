<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\QuotationService;
use App\Support\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * - Komponen modal: tombol submit di slot footer harus terhubung ke form di isi modal (sebelumnya berada
 *   di luar form, jadi "Simpan" tidak melakukan apa-apa di 13 halaman master).
 * - Penawaran: produk yang dihitung per qty tidak menyimpan ukuran; cetakan berisi kalimat surat dan terbilang.
 */
class ModalDanPenawaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_tombol_submit_di_footer_modal_terhubung_ke_form_di_isi_modal(): void
    {
        $html = Blade::render('<x-modal id="uji" title="Uji"><form method="POST" action="/x">@csrf<input name="a"><x-slot:footer><button type="button">Batal</button><button type="submit">Simpan</button></x-slot:footer></form></x-modal>');

        $this->assertStringContainsString('<form id="modal-uji-form"', $html);
        $this->assertStringContainsString('<button form="modal-uji-form" type="submit">Simpan</button>', $html);
        $this->assertStringContainsString('<button type="button">Batal</button>', $html);
    }

    public function test_form_yang_sudah_punya_id_dipakai_apa_adanya_dan_tombol_ber_form_tidak_disentuh(): void
    {
        $html = Blade::render('<x-modal id="uji" title="Uji"><form id="formEdit" method="POST"></form><x-slot:footer><button type="submit">A</button><button type="submit" form="lain">B</button></x-slot:footer></x-modal>');

        $this->assertStringContainsString('<button form="formEdit" type="submit">A</button>', $html);
        $this->assertStringContainsString('<button type="submit" form="lain">B</button>', $html);
    }

    public function test_modal_dengan_dua_form_tidak_ditebak(): void
    {
        $html = Blade::render('<x-modal id="uji" title="Uji"><form id="a"></form><form id="b"></form><x-slot:footer><button type="submit">S</button></x-slot:footer></x-modal>');

        $this->assertStringContainsString('<button type="submit">S</button>', $html);
    }

    public function test_halaman_master_tidak_punya_tombol_simpan_modal_yang_lepas_dari_form(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        foreach (['/jasa', '/jasa-jahit', '/golongan', '/satuan', '/kategori', '/merk', '/gudang', '/akun', '/konsumen-deposit'] as $uri) {
            $html = $this->actingAs($admin)->get($uri)->assertOk()->getContent();
            // Setiap footer modal: tombol submit harus membawa atribut form=.
            preg_match_all('/<div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 shrink-0">(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $html, $kaki);
            foreach ($kaki[1] as $isi) {
                preg_match_all('/<button\b[^>]*type="submit"[^>]*>/', $isi, $tombol);
                foreach ($tombol[0] as $t) {
                    $this->assertStringContainsString('form="', $t, "{$uri}: tombol submit modal tanpa form");
                }
            }
        }
    }

    public function test_terbilang(): void
    {
        $this->assertSame('Seribu lima ratus rupiah', Terbilang::rupiah(1500));
        $this->assertSame('Satu juta dua ratus lima puluh ribu lima ratus rupiah', Terbilang::rupiah(1250500));
        $this->assertSame('Seratus sebelas rupiah', Terbilang::rupiah(111));
        $this->assertSame('Dua puluh rupiah', Terbilang::rupiah(20));
        $this->assertSame('Nol rupiah', Terbilang::rupiah(0));
    }

    public function test_penawaran_produk_per_qty_tidak_menyimpan_ukuran_dan_cetakan_berisi_surat(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($admin);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pak Budi', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $gol]);
        $kartu = DB::table('produk')->insertGetId(['nama_produk' => 'Kartu Nama', 'metode_hitung' => 'quantity']);
        DB::table('setting_harga')->insert(['id_produk' => $kartu, 'id_golongan' => $gol, 'harga_jual' => 50000]);

        $q = QuotationService::simpan([
            'tanggal' => now()->toDateString(), 'berlaku_sampai' => now()->addDays(7)->toDateString(), 'id_konsumen' => $kons, 'nama_pelanggan' => '', 'diskon' => 0,
            'items' => [['tipe' => 'Produk', 'id_produk' => $kartu, 'p' => 300, 'l' => 500, 'qty' => 2]],
        ]);

        $item = $q->items->first();
        $this->assertEquals(0, (float) $item->p_ukuran);
        $this->assertEquals(0, (float) $item->l_ukuran);
        $this->assertEquals(100000, (float) $q->total);

        $this->get("/quotation/{$q->id}/cetak")->assertOk()
            ->assertSee('Dengan hormat')->assertSee('Penawaran harga pekerjaan cetak')
            ->assertSee('Terbilang: Seratus ribu rupiah')->assertSee('7 hari sejak tanggal surat')
            ->assertSee('Harga dihitung untuk jumlah dan bahan')->assertSee('Disetujui oleh')
            ->assertDontSee('300 x 500');

        // Form: opsi produk membawa metode hitung untuk menampilkan/menyembunyikan P dan L.
        $this->get('/quotation/create')->assertOk()->assertSee('data-metode="quantity"', false)->assertSee('data-blok-ukuran', false);
    }
}
