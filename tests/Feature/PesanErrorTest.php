<?php

namespace Tests\Feature;

use App\Support\PesanError;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Audit Fase 2 (M-1): pesan bisnis lolos ke operator, detail teknis tidak pernah bocor tapi tetap masuk log. */
class PesanErrorTest extends TestCase
{
    public function test_pesan_bisnis_diteruskan_apa_adanya_dengan_dan_tanpa_awalan(): void
    {
        $this->assertSame('Periode akuntansi sudah ditutup.', PesanError::untuk(new \RuntimeException('Periode akuntansi sudah ditutup.')));
        $this->assertSame('Gagal membayar: Nominal melebihi sisa hutang.', PesanError::untuk(new \RuntimeException('Nominal melebihi sisa hutang.'), 'Gagal membayar'));
        // KasirController melempar \Exception polos sebagai pesan bisnis.
        $this->assertSame('Stok tidak cukup', PesanError::untuk(new \Exception('Stok tidak cukup')));
    }

    public function test_pesan_abort_http_dianggap_bisnis(): void
    {
        $this->assertSame('Anda tidak berhak.', PesanError::untuk(new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Anda tidak berhak.')));
    }

    public function test_error_sql_tidak_bocor_dan_dilaporkan_ke_log(): void
    {
        Log::spy();
        $e = new QueryException('mysql', 'select * from rahasia where password = ?', ['x'], new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1451 tabel_rahasia'));

        $pesan = PesanError::untuk($e, 'Gagal menyimpan');

        $this->assertStringNotContainsString('SQLSTATE', $pesan);
        $this->assertStringNotContainsString('rahasia', $pesan);
        $this->assertStringNotContainsString('select', strtolower($pesan));
        $this->assertStringStartsWith('Gagal menyimpan. ', $pesan);
        $this->assertStringContainsString(PesanError::UMUM, $pesan);
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_query_exception_yang_turunan_runtime_exception_tetap_dianggap_teknis(): void
    {
        $e = new QueryException('mysql', 'insert into x', [], new \PDOException('boom'));

        $this->assertInstanceOf(\RuntimeException::class, $e); // jebakan: turunan RuntimeException
        $this->assertFalse(PesanError::bisnis($e));
    }

    public function test_type_error_dan_error_php_tidak_bocor(): void
    {
        $this->assertFalse(PesanError::bisnis(new \TypeError('Argument #1 must be of type int')));
        $this->assertStringNotContainsString('Argument', PesanError::untuk(new \TypeError('Argument #1 must be of type int')));
        $this->assertFalse(PesanError::bisnis(new \Illuminate\Database\Eloquent\ModelNotFoundException('No query results for model [App\Models\X]')));
    }
}
