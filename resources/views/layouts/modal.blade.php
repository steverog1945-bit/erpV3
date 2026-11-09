{{--
    DEPRECATED - pakai <x-modal>.

    File ini pernah jadi salinan komponen modal sebelum komponennya
    ada. Sekarang isinya sudah dibuang: <x-modal> adalah satu-
    satunya tempat markup modal didefinisikan, supaya radius, tinggi,
    font, animasi, Escape, dan klik-luar tidak mungkin berbeda antar
    halaman.

    Isi lama juga sudah menyimpang: radius 16px, tombol tutup tanpa
    hitung tinggi, panel memakai overflow-y-auto sehingga header ikut
    tergulir, dan tidak punya penanda data-modal-root sehingga Escape
    serta klik-luar tidak pernah bekerja di halaman mana pun yang
    memakainya.

    Sengaja tidak dihapus: kalau view lama di luar repo masih
    merujuk ke 'layouts.modal', file ini akan diam-diam merender
    modal yang rusak. Lebih aman meneruskan ke komponen yang benar.
--}}
<x-modal :id="$id" :title="$title ?? null" :maxWidth="$maxWidth ?? 'lg'">
    {{ $slot }}

    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-modal>
