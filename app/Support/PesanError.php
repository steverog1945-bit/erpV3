<?php

namespace App\Support;

/**
 * Satu-satunya jalur mengubah exception menjadi pesan untuk pengguna
 * (audit Fase 2, M-1). Pesan BISNIS (yang memang ditulis untuk operator:
 * "Periode akuntansi sudah ditutup", "Stok tidak cukup", dst) diteruskan
 * apa adanya. Kegagalan TEKNIS (SQL, TypeError, file, dst) tidak boleh
 * bocor ke layar - detailnya masuk log lewat report(), pengguna hanya
 * menerima kalimat umum.
 *
 * Yang dianggap bisnis: \Exception polos (dilempar langsung oleh kode
 * aplikasi, mis. KasirController), \RuntimeException, \DomainException,
 * \LogicException dan \InvalidArgumentException BUATAN aplikasi. Turunan
 * yang berasal dari framework/driver (QueryException & PDOException -
 * keduanya turunan RuntimeException!, ErrorException, TypeError, dst)
 * SELALU dianggap teknis.
 */
class PesanError
{
    public const UMUM = 'Terjadi kesalahan sistem. Tidak ada data yang tersimpan; coba lagi atau hubungi Administrator.';

    public static function bisnis(\Throwable $e): bool
    {
        if ($e instanceof \PDOException || $e instanceof \Error || $e instanceof \ErrorException) {
            return false;
        }

        // abort(403, '...') berisi kalimat yang memang ditulis untuk operator.
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return true;
        }

        // Exception framework/driver (Illuminate\..., Symfony\..., PDO) bukan pesan untuk operator.
        $kelas = get_class($e);
        if (str_starts_with($kelas, 'Illuminate\\') || str_starts_with($kelas, 'Symfony\\') || str_starts_with($kelas, 'PDO')) {
            return false;
        }

        return $e instanceof \Exception;
    }

    /**
     * @param  string|null  $awalan  Mis. "Gagal menyimpan" -> "Gagal menyimpan: <pesan>" / "Gagal menyimpan. <umum>"
     */
    public static function untuk(\Throwable $e, ?string $awalan = null): string
    {
        if (self::bisnis($e)) {
            return ($awalan ? $awalan . ': ' : '') . $e->getMessage();
        }

        report($e);

        return ($awalan ? $awalan . '. ' : '') . self::UMUM;
    }
}
