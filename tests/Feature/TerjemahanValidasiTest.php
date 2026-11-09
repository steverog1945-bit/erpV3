<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LOKALISASI PESAN VALIDASI.
 *
 * Sebelum fix: lang/ tidak ada, jadi walau locale = id pesan validasi
 * tetap bahasa Inggris ("The nama supplier field is required.").
 * Setelah: vendor laravel-lang/lang + lang:add id + lang/id/validation.php.
 *
 * Tes mewakili tiap tipe aturan pada level HTTP (production code path)
 * supaya tidak bisa "dibohongi" pada runtime tertentu.
 */
class TerjemahanValidasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $idRole = (int) DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $idRole]);
    }

    public function test_locale_adalah_indonesia(): void
    {
        $this->assertSame('id', app()->getLocale());
    }

    /** required -> "Nama supplier wajib diisi." (bukan "The nama supplier field is required.") */
    public function test_pesan_required_berbahasa_indonesia(): void
    {
        $r = $this->actingAs($this->admin)
            ->from('/supplier')
            ->post('/supplier', ['nama_supplier' => '', 'kontak' => '']);

        $r->assertSessionHasErrors(['nama_supplier']);
        $this->assertStringContainsString('Nama supplier wajib diisi.', session('errors')->first());
        $this->assertStringNotContainsString('field is required', session('errors')->first());
    }

    /** unique -> "Nama kategori sudah ada sebelumnya." (bukan "has already been taken.") */
    public function test_pesan_unique_berbahasa_indonesia(): void
    {
        DB::table('kategori')->insert(['nama_kategori' => 'Duplikat', 'kode_kategori' => 'DPL']);

        $r = $this->actingAs($this->admin)
            ->from('/kategori')
            ->post('/kategori', ['nama_kategori' => 'Duplikat', 'kode_kategori' => 'X01']);

        $r->assertSessionHasErrors(['nama_kategori']);
        $this->assertStringContainsString('Nama kategori sudah ada', session('errors')->first());
        $this->assertStringNotContainsString('has already been taken', session('errors')->first());
    }

    /** unique pada aturan tanpa pesan kustom -> terjemahan kerangka id */
    public function test_pesan_unique_kerangka_berbahasa_indonesia(): void
    {
        DB::table('users')->where('id_user', $this->admin->id_user)->update(['username' => 'adminuji']);

        $v = validator(['username' => 'adminuji'], ['username' => 'unique:users,username']);

        $this->assertStringContainsString('Username sudah ada sebelumnya.', $v->errors()->first('username'));
    }

    /** exists -> "Id bahan yang dipilih tidak valid." */
    public function test_pesan_exists_berbahasa_indonesia(): void
    {
        $r = $this->actingAs($this->admin)
            ->from('/waste/create')
            ->post('/waste', ['id_bahan' => 999999, 'jenis' => 'Normal', 'alasan' => 'rusak']);

        $r->assertSessionHasErrors(['id_bahan']);
        $this->assertStringContainsString('Id bahan yang dipilih tidak valid.', session('errors')->first());
    }

    /** numeric -> "Harga default harus berupa angka." */
    public function test_pesan_numeric_berbahasa_indonesia(): void
    {
        $r = $this->actingAs($this->admin)
            ->from('/finishing')
            ->post('/finishing', ['nama_finishing' => 'Fin Audit', 'harga_default' => 'abc', 'metode_hitung' => 'quantity']);

        $r->assertSessionHasErrors(['harga_default']);
        $this->assertStringContainsString('Harga default harus berupa angka.', session('errors')->first());
    }

    /** date -> "Tanggal bukan tanggal yang valid." */
    public function test_pesan_date_berbahasa_indonesia(): void
    {
        $r = $this->actingAs($this->admin)
            ->from('/quotation/create')
            ->post('/quotation', ['tanggal' => 'bukan-tanggal', 'id_konsumen' => 1]);

        $r->assertSessionHasErrors(['tanggal']);
        $this->assertStringContainsString('Tanggal bukan tanggal yang valid.', session('errors')->first());
    }

    /** min (string) -> "Alasan minimal berisi 5 karakter." */
    public function test_pesan_min_string_berbahasa_indonesia(): void
    {
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Gol Uji']);
        $idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Kons Uji', 'telepon' => '-', 'id_golongan' => $idGol, 'tipe_konsumen' => 'temporary', 'tgl_registrasi' => now(), 'nama_konsumen_manual' => 'Kons Uji']);

        $r = $this->actingAs($this->admin)
            ->from('/konsumen-deposit')
            ->post('/konsumen-deposit/kembalikan', [
                'id_konsumen' => $idKons, 'nominal' => 1000, 'metode_bayar' => 'Tunai', 'alasan' => 'abc',
            ]);

        $r->assertSessionHasErrors(['alasan']);
        $this->assertStringContainsString('Alasan minimal berisi 5 karakter.', session('errors')->first());
    }

    /** confirmed tidak dipakai di controller; tetap diperiksa pada level validator
     *  supaya tidak diam-diam kembali Inggris. */
    public function test_pesan_confirmed_berbahasa_indonesia(): void
    {
        $v = validator(['password' => 'rahasia123', 'password_confirmation' => 'berbeda'], [
            'password' => 'confirmed',
        ]);

        $this->assertStringContainsString('Konfirmasi password tidak cocok.', $v->errors()->first('password'));
    }
}