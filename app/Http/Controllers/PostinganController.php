<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostKategori;
use App\Support\HtmlBersih;
use App\Support\Izin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Info & Panduan - postingan internal ala blog. Semua yang berhak (menu 67) boleh membaca postingan yang sudah terbit;
 * menulis/mengubah/menghapus mengikuti hak tambah/ubah/hapus. Draft & terjadwal hanya terlihat oleh pengelola.
 */
class PostinganController extends Controller
{
    private const PER_HALAMAN = 9;

    private function pengelola(): bool
    {
        return Izin::boleh(Auth::user(), [67], 'add') || Izin::boleh(Auth::user(), [67], 'edit');
    }

    public function index(Request $request)
    {
        return view('postingan.index', self::dataDaftar($request, route('postingan.index')));
    }

    /** Data daftar bersama (halaman /postingan dan tab Info di Dashboard). $basisUrl = alamat dasar link pagination. */
    public static function dataDaftar(Request $request, string $basisUrl): array
    {
        $pengelola = Izin::boleh(Auth::user(), [67], 'add') || Izin::boleh(Auth::user(), [67], 'edit');

        $kategoriSlug = $request->get('kategori');
        $tag = trim((string) $request->get('tag', ''));
        $cari = trim((string) $request->get('cari', ''));
        $status = $pengelola ? $request->get('status') : null; // terbit | terjadwal | draft

        $dasar = Post::with(['kategori', 'penulis.karyawan'])
            ->when(!$pengelola, fn ($q) => $q->terbit())
            ->when($pengelola && $status === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($pengelola && $status === 'terbit', fn ($q) => $q->terbit())
            ->when($pengelola && $status === 'terjadwal', fn ($q) => $q->where('status', 'terbit')->where('terbit_at', '>', now()))
            ->when($kategoriSlug, fn ($q) => $q->whereHas('kategori', fn ($k) => $k->where('slug', $kategoriSlug)))
            ->when($tag !== '', fn ($q) => $q->where('tags', 'like', '%' . $tag . '%'))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('judul', 'like', "%{$cari}%")->orWhere('ringkasan', 'like', "%{$cari}%")->orWhere('isi', 'like', "%{$cari}%")->orWhere('tags', 'like', "%{$cari}%")));

        $tanpaFilter = !$kategoriSlug && $tag === '' && $cari === '' && !$status;
        $unggulan = null;
        if ($tanpaFilter && (int) $request->get('page', 1) === 1) {
            $unggulan = (clone $dasar)->terbit()->orderByDesc('disematkan')->orderByDesc('terbit_at')->orderByDesc('id')->first();
        }

        $posts = $dasar->when($unggulan, fn ($q) => $q->where('id', '!=', $unggulan->id))
            ->orderByDesc('disematkan')->orderByDesc('terbit_at')->orderByDesc('id')
            ->paginate(self::PER_HALAMAN)->withQueryString()->withPath($basisUrl);

        $kategoriList = PostKategori::withCount(['posts' => fn ($q) => $q->terbit()])->orderBy('nama')->get();
        $tagPopuler = Post::terbit()->whereNotNull('tags')->pluck('tags')->flatMap(fn ($t) => array_map('trim', explode(',', $t)))->filter()
            ->countBy()->sortDesc()->take(12)->keys();

        return compact('posts', 'unggulan', 'kategoriList', 'tagPopuler', 'pengelola', 'kategoriSlug', 'tag', 'cari', 'status');
    }


    public function show(string $slug)
    {
        $pengelola = $this->pengelola();
        $post = Post::with(['kategori', 'penulis.karyawan'])->where('slug', $slug)->firstOrFail();
        if (!$pengelola && !Post::terbit()->whereKey($post->id)->exists()) {
            abort(404);
        }

        if ($post->status === 'terbit' && (!$post->terbit_at || !$post->terbit_at->isFuture())) {
            $post->increment('dibaca');
        }

        $terkait = Post::with('kategori')->terbit()->where('id', '!=', $post->id)
            ->when($post->id_kategori, fn ($q) => $q->where('id_kategori', $post->id_kategori))
            ->orderByDesc('terbit_at')->limit(3)->get();
        $sebelumnya = Post::terbit()->where('id', '!=', $post->id)->where('terbit_at', '<', $post->terbit_at ?? now())->orderByDesc('terbit_at')->first();
        $berikutnya = Post::terbit()->where('id', '!=', $post->id)->where('terbit_at', '>', $post->terbit_at ?? now())->orderBy('terbit_at')->first();

        return view('postingan.show', compact('post', 'terkait', 'sebelumnya', 'berikutnya', 'pengelola'));
    }

    public function create()
    {
        return view('postingan.form', ['post' => new Post(['status' => 'draft']), 'kategoriList' => PostKategori::orderBy('nama')->get()]);
    }

    public function edit(int $id)
    {
        return view('postingan.form', ['post' => Post::findOrFail($id), 'kategoriList' => PostKategori::orderBy('nama')->get()]);
    }

    public function store(Request $request)
    {
        $post = new Post();
        $this->simpan($request, $post);

        return redirect()->route('postingan.show', $post->slug)->with('success', $post->status === 'draft' ? 'Draft postingan disimpan.' : 'Postingan berhasil diterbitkan.');
    }

    public function update(Request $request, int $id)
    {
        $post = Post::findOrFail($id);
        $this->simpan($request, $post);

        return redirect()->route('postingan.show', $post->slug)->with('success', 'Postingan diperbarui.');
    }

    public function destroy(int $id)
    {
        $post = Post::findOrFail($id);
        $post->delete(); // soft delete: bisa dipulihkan dari database bila salah hapus

        return redirect()->route('postingan.index')->with('success', 'Postingan dihapus.');
    }

    /** Unggah gambar isi/sampul dari editor. Mengembalikan alamat yang bisa langsung ditampilkan. */
    public function unggahGambar(Request $request)
    {
        $request->validate(['gambar' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:5120']);
        $path = $request->file('gambar')->store('posts/isi', 'public');

        return response()->json(['url' => asset('storage/' . $path), 'path' => $path]);
    }

    private function simpan(Request $request, Post $post): void
    {
        $data = $request->validate([
            'judul' => 'required|string|max:200',
            'ringkasan' => 'nullable|string|max:300',
            'isi' => 'required|string',
            'id_kategori' => 'nullable|integer|exists:post_kategori,id',
            'kategori_baru' => 'nullable|string|max:60',
            'tags' => 'nullable|string|max:255',
            'status' => 'required|in:draft,terbit',
            'terbit_at' => 'nullable|date',
            'sampul' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $isi = HtmlBersih::bersihkan($data['isi']);
        // Alamat gambar dari storage disimpan sebagai penanda supaya tidak terikat domain/subfolder.
        // asset() bisa menghasilkan URL absolut (http://...) ATAU path relatif
        // (root-relative) tergantung ASSET_URL - jadi kedua bentuk dicocokkan.
        // Kalau hanya bentuk absolut yang dikenali, gambar yang baru diunggah
        // akan tersimpan memakai domain/subfolder saat itu.
        $isi = preg_replace('#https?://[^"\']*?/storage/(posts/[^"\']+)#i', '@@STORAGE@@/$1', $isi);
        $isi = preg_replace('#(?<![\w/])/[^"\']*?/storage/(posts/[^"\']+)#i', '@@STORAGE@@/$1', $isi);
        if (trim(strip_tags($isi, '<img>')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['isi' => 'Isi postingan tidak boleh kosong.']);
        }

        $idKategori = $data['id_kategori'] ?? null;
        if (!empty($data['kategori_baru'])) {
            $idKategori = PostKategori::firstOrCreate(['slug' => Str::slug($data['kategori_baru'])], ['nama' => trim($data['kategori_baru'])])->id;
        }

        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => Str::limit(trim($t), 30, ''))->filter()->unique()->take(8)->implode(', ');
        $ringkasan = trim((string) ($data['ringkasan'] ?? '')) ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#</(p|h[1-6]|li|blockquote)>#i', ' ', $isi)))), 160);

        $terbitAt = null;
        if ($data['status'] === 'terbit') {
            $terbitAt = !empty($data['terbit_at']) ? \Carbon\Carbon::parse($data['terbit_at']) : ($post->terbit_at ?? now());
        }

        $sampul = $post->gambar_sampul;
        if ($request->hasFile('sampul')) {
            if ($sampul) {
                Storage::disk('public')->delete($sampul);
            }
            $sampul = $request->file('sampul')->store('posts/sampul', 'public');
        } elseif ($request->boolean('hapus_sampul') && $sampul) {
            Storage::disk('public')->delete($sampul);
            $sampul = null;
        }

        $post->fill([
            'judul' => trim($data['judul']),
            'slug' => $post->exists ? $post->slug : Post::buatSlug($data['judul']),
            'ringkasan' => $ringkasan,
            'isi' => $isi,
            'gambar_sampul' => $sampul,
            'id_kategori' => $idKategori,
            'tags' => $tags ?: null,
            'status' => $data['status'],
            'terbit_at' => $terbitAt,
            'disematkan' => $request->boolean('disematkan'),
            'tampil_publik' => $request->boolean('tampil_publik'),
            'id_penulis' => $post->id_penulis ?? Auth::id(),
            'menit_baca' => HtmlBersih::menitBaca($isi),
        ])->save();
    }
}
