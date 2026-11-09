<?php

namespace App\Exceptions;

/**
 * Item nota tidak valid (produk/jasa/finishing tidak ditemukan, qty
 * tidak sah, harga belum diatur, dan seterusnya).
 *
 * Dipakai supaya loop validasi item bisa dipindah ke method private
 * tanpa harus melompat balik ke controller di sepuluh titik berbeda.
 * Pemanggil mengubahnya jadi JSON 422 dengan pesan yang sama seperti
 * sebelumnya - BUKAN 500, karena ini kesalahan input operator, bukan
 * kegagalan sistem.
 */
class ValidasiNotaException extends \RuntimeException
{
    public int $status = 422;

    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
