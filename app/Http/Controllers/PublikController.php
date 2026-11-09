<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostKategori;
use Illuminate\Http\Request;

/**
 * Halaman depan (tanpa login): daftar tulisan yang ditandai "tampil di halaman depan" dan halaman bacanya.
 * Hanya tulisan berstatus terbit DAN ber-tanda publik yang keluar dari sini; SOP dan pengumuman internal
 * tetap hanya bisa dibaca setelah login (PostinganController).
 */
class PublikController extends Controller
{
    private const PER_HALAMAN = 9;

    /** Data halaman depan. Dipakai AuthController::showLogin (halaman depan adalah juga halaman masuk). */
    public static function dataBeranda(Request $request): array
    {
        $kategoriSlug = (string) $request->query('kategori', '');
        $cari = trim((string) $request->query('cari', ''));
        $tanpaFilter = $kategoriSlug === '' && $cari === '';

        $dasar = Post::with('kategori')->publik()
            ->when($kategoriSlug !== '', fn ($q) => $q->whereHas('kategori', fn ($k) => $k->where('slug', $kategoriSlug)))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('judul', 'like', "%{$cari}%")->orWhere('ringkasan', 'like', "%{$cari}%")->orWhere('tags', 'like', "%{$cari}%")));

        $urut = fn ($q) => $q->orderByDesc('disematkan')->orderByDesc('terbit_at')->orderByDesc('id');

        $unggulan = $tanpaFilter ? $urut(clone $dasar)->first() : null;
        $posts = $urut($dasar->when($unggulan, fn ($q) => $q->where('id', '!=', $unggulan->id)))
            ->paginate(self::PER_HALAMAN)->withQueryString();

        $kategoriList = PostKategori::whereHas('posts', fn ($q) => $q->publik())->orderBy('nama')->get();

        return compact('posts', 'unggulan', 'kategoriList', 'kategoriSlug', 'cari');
    }

    /** Jawaban untuk saring, cari, dan "muat lagi" tanpa memuat ulang halaman. */
    public static function jawabanDaftar(array $data)
    {
        return response()->json([
            'html' => view('publik._kartu', ['posts' => $data['posts']])->render(),
            'berikut' => $data['posts']->hasMorePages() ? $data['posts']->currentPage() + 1 : null,
            'jumlah' => $data['posts']->total(),
        ]);
    }

    public function baca(string $slug)
    {
        $post = Post::with(['kategori', 'penulis.karyawan'])->publik()->where('slug', $slug)->firstOrFail();
        $post->increment('dibaca');

        $lain = Post::with('kategori')->publik()->where('id', '!=', $post->id)
            ->orderByRaw('id_kategori = ? desc', [$post->id_kategori ?? 0])->orderByDesc('terbit_at')->limit(3)->get();

        return view('publik.baca', compact('post', 'lain'));
    }
}
