@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-5xl mx-auto">

    <x-page-header title="Pengaturan Aplikasi" subtitle="Identitas perusahaan, format nota, dan aturan kas - semua pengaturan aplikasi dalam satu tempat." />

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl mb-6 font-medium">
            {{ session('success') }}
        </div>
    @endif

    {{-- TAB - satu form, satu tombol Simpan untuk semua tab (bukan submit
         terpisah per tab) supaya perubahan di beberapa tab sekaligus tidak
         perlu disimpan satu-satu. --}}
    <div class="flex gap-1 mb-5 border-b border-slate-200 overflow-x-auto">
        <button type="button" onclick="pindahTabPengaturan('umum')" id="tab-btn-umum" class="tab-btn-pengaturan px-5 py-3 text-xs font-medium uppercase tracking-widest border-b-2 border-indigo-600 text-indigo-600 whitespace-nowrap">
            <i class="fas fa-building mr-1.5"></i> Umum
        </button>
        <button type="button" onclick="pindahTabPengaturan('nota')" id="tab-btn-nota" class="tab-btn-pengaturan px-5 py-3 text-xs font-medium uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 whitespace-nowrap">
            <i class="fas fa-receipt mr-1.5"></i> Nota
        </button>
        @if(str_starts_with((string) (optional(auth()->user()?->role)->nama_role ?? ''), 'Administrator'))
        <button type="button" onclick="pindahTabPengaturan('kas')" id="tab-btn-kas" class="tab-btn-pengaturan px-5 py-3 text-xs font-medium uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 whitespace-nowrap">
            <i class="fas fa-cash-register mr-1.5"></i> Kas
        </button>
        @endif
        @if(str_starts_with((string) (optional(auth()->user()?->role)->nama_role ?? ''), 'Administrator'))
            <a href="{{ route('pengaturan-aplikasi.tema.index') }}" class="px-5 py-3 text-xs font-medium uppercase tracking-widest text-slate-500 hover:text-indigo-600 whitespace-nowrap">
                <i class="fas fa-palette mr-1.5"></i> Tema &amp; Tampilan
            </a>
        @endif
    </div>

    <form action="{{ route('pengaturan-aplikasi.update') }}" method="POST" enctype="multipart/form-data" id="form-pengaturan-aplikasi">
        @csrf

        {{-- TAB UMUM --}}
        <div id="tab-panel-umum" class="tab-panel-pengaturan">
            <x-card class="space-y-6">
                {{-- MODE TAMPILAN (DARK MODE): hanya Administrator --}}
                @if(str_starts_with((string) (optional(auth()->user()?->role)->nama_role ?? ''), 'Administrator'))
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <span class="block text-sm font-medium text-slate-700">Mode Tampilan</span>
                        <span class="block text-xs text-slate-400 mt-0.5">Gelap / terang. Preferensi disimpan di perangkat ini.</span>
                    </div>
                    <button type="button" onclick="ubahTema()" class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">
                        <i class="fas fa-moon"></i> Ganti Tema
                    </button>
                </div>
                @endif
                <div class="flex items-center gap-5">
                    <div class="w-20 h-20 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden shrink-0">
                        @if($setting->logo_path)
                            <img loading="lazy" decoding="async" id="preview-logo" src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo" class="w-full h-full object-contain">
                        @else
                            <i id="preview-logo-placeholder" class="fas fa-image text-slate-500 text-2xl"></i>
                            <img loading="lazy" decoding="async" id="preview-logo" src="" alt="Logo" class="w-full h-full object-contain hidden" 
                        @endif
                    </div>
                    <x-form.group label="Logo Perusahaan" name="logo" help="PNG/JPG/WEBP, maks 2MB. Kosongkan kalau tidak ingin mengubah." dalam>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                            onchange="previewLogoAplikasi(this)"
                            class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-xs font-medium text-slate-600 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-600 file:text-white">
                    </x-form.group>
                </div>


                {{-- TAMPILAN LOGO DI SIDEBAR --}}
                @php
                    $lg = [
                        'ukuran' => in_array($setting->logo_ukuran, ['kecil', 'sedang', 'besar'], true) ? $setting->logo_ukuran : 'sedang',
                        'gaya' => in_array($setting->logo_gaya, ['polos', 'kaca', 'putih'], true) ? $setting->logo_gaya : 'polos',
                        'ringkas' => $setting->logo_ringkas === 'penuh' ? 'penuh' : 'potong',
                    ];
                @endphp
                <div class="rounded-2xl border border-slate-100 p-5 space-y-5">
                    <div class="text-[11px] font-medium text-slate-400 uppercase tracking-widest"><i class="fas fa-swatchbook mr-1.5"></i> Tampilan Logo di Sidebar</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-5">
                            <x-form.group label="Ukuran Logo" name="logo_ukuran" dalam>
                                <x-form.select name="logo_ukuran" id="opt-logo-ukuran">
                                    <option value="kecil" @selected($lg['ukuran'] === 'kecil')>Kecil</option>
                                    <option value="sedang" @selected($lg['ukuran'] === 'sedang')>Sedang (disarankan)</option>
                                    <option value="besar" @selected($lg['ukuran'] === 'besar')>Besar</option>
                                </x-form.select>
                            </x-form.group>
                            <x-form.group label="Gaya Latar Logo" name="logo_gaya" help="Polos = tanpa kotak, dengan kilau lembut. Kaca = kotak transparan. Putih = kotak putih (untuk logo gelap)." dalam>
                                <x-form.select name="logo_gaya" id="opt-logo-gaya">
                                    <option value="polos" @selected($lg['gaya'] === 'polos')>Polos (tanpa kotak)</option>
                                    <option value="kaca" @selected($lg['gaya'] === 'kaca')>Kaca (transparan)</option>
                                    <option value="putih" @selected($lg['gaya'] === 'putih')>Kotak putih</option>
                                </x-form.select>
                            </x-form.group>
                            <x-form.group label="Saat Sidebar Diringkas" name="logo_ringkas" help="Potong = tampilkan lambang di sisi kiri logo. Penuh = logo utuh diperkecil. Jika Logo Ikon diunggah, ikon itu yang dipakai." dalam>
                                <x-form.select name="logo_ringkas" id="opt-logo-ringkas">
                                    <option value="potong" @selected($lg['ringkas'] === 'potong')>Potong otomatis (lambang kiri)</option>
                                    <option value="penuh" @selected($lg['ringkas'] === 'penuh')>Logo penuh diperkecil</option>
                                </x-form.select>
                            </x-form.group>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Pratinjau (latar sidebar)</div>
                                <div class="rounded-2xl px-6 py-8 flex items-center justify-center min-h-[130px]" style="background:#1A231F">
                                    @if($setting->logo_path)
                                        <div id="pratinjau-logo" class="sidebar-logo logo-{{ $lg['ukuran'] }} logo-gaya-{{ $lg['gaya'] }}">
                                            <img loading="lazy" decoding="async" id="pratinjau-logo-img" class="logo-full" src="{{ asset('storage/' . $setting->logo_path) }}" alt="Pratinjau logo">
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-400">Unggah logo untuk melihat pratinjau.</span>
                                    @endif
                                </div>
                            </div>
                            <x-form.group label="Logo Ikon (opsional, persegi)" name="logo_ikon" help="Dipakai saat sidebar diringkas. PNG/JPG/WEBP, maks 1MB." dalam>
                                <input type="file" name="logo_ikon" accept="image/png,image/jpeg,image/webp"
                                    class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-xs font-medium text-slate-600 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-600 file:text-white">
                            </x-form.group>
                            @if($setting->logo_ikon_path)
                                <div class="flex items-center gap-3 ml-2">
                                    <img loading="lazy" decoding="async" src="{{ asset('storage/' . $setting->logo_ikon_path) }}" alt="Logo ikon" class="w-10 h-10 rounded-lg object-contain bg-slate-800 p-1">
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <input type="checkbox" name="hapus_logo_ikon" value="1" class="rounded border-slate-300 text-indigo-600"> Hapus logo ikon
                                    </label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <x-form.group label="Nama Aplikasi" name="nama_aplikasi" required dalam>
                    <x-form.input type="text" name="nama_aplikasi" value="{{ old('nama_aplikasi', $setting->nama_aplikasi) }}" required />
                </x-form.group>

                <x-form.group label="Alamat" name="alamat" dalam>
                    <textarea name="alamat" rows="3" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">{{ old('alamat', $setting->alamat) }}</textarea>
                </x-form.group>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-form.group label="Telepon" name="telepon" dalam>
                        <x-form.input type="text" name="telepon" value="{{ old('telepon', $setting->telepon) }}" placeholder="08xx / 021xx" />
                    </x-form.group>

                    <x-form.group label="Fax" name="fax" dalam>
                        <x-form.input type="text" name="fax" value="{{ old('fax', $setting->fax) }}" placeholder="- (kosongkan kalau tidak ada)" />
                    </x-form.group>

                    <x-form.group label="Email" name="email" dalam>
                        <x-form.input type="email" name="email" value="{{ old('email', $setting->email) }}" placeholder="info@perusahaan.com" />
                    </x-form.group>

                    <x-form.group label="Website" name="website" dalam>
                        <x-form.input type="text" name="website" value="{{ old('website', $setting->website) }}" placeholder="http://perusahaan.com" />
                    </x-form.group>

                    <x-form.group label="Alamat Akses Aplikasi (untuk QR di HP)" name="url_akses" dalam>
                        <x-form.input type="text" name="url_akses" value="{{ old('url_akses', $setting->url_akses) }}" placeholder="http://192.168.1.10/percetakan-app/public" />
                        <p class="text-[11px] text-slate-400 mt-1.5 ml-2">Alamat yang bisa dibuka dari HP (satu Wi-Fi dengan komputer kasir, atau alamat internet bila sudah online). Dipakai pada QR di cetakan SPK. Kosong = alamat halaman saat ini.</p>
                    </x-form.group>
                </div>

                {{-- Toggle "Wajib persetujuan Administrator untuk Edit Nota" DIHAPUS.

                     Alasannya: alur edit nota yang lama membatalkan nota,
                     mengembalikan seluruh stok, dan membalik jurnal - itu
                     operasi besar, makanya perlu persetujuan. Sekarang Edit
                     Nota memakai alur SELISIH: nota tidak dibatalkan, item
                     yang tidak disentuh sama sekali tidak menyentuh stok, dan
                     yang efek hanya sebesar selisihnya. Koreksi nominal
                     seperti ini tidak perlu persetujuan Administrator -
                         sama seperti koreksi harga di layar pembayaran kasir.

                     Tabel nota_edit_request & endpoint alur lama tetap
                     dibiarkan supaya riwayat permintaan yang sudah pernah
                     dibuat masih bisa dibaca. --}}

                <div class="pt-4 flex justify-end border-t border-slate-100">
                    <x-button type="submit" class="!px-10">Simpan Pengaturan</x-button>
                </div>
            </x-card>
        </div>

        {{-- TAB NOTA - 2 kolom: form di kiri, preview LIVE di kanan (sticky)
             supaya admin langsung lihat dampak visual dari format/footer yang
             diubah, tidak perlu cetak nota sungguhan cuma untuk mengecek. --}}
        <div id="tab-panel-nota" class="tab-panel-pengaturan hidden">
            <div class="flex flex-col lg:flex-row gap-5 items-start">
                <div class="w-full lg:w-[58%] space-y-5">
                    <x-card class="space-y-6">
                        <x-form.group label="Format Cetak Nota" name="format_nota" help="Berlaku GLOBAL untuk semua nota - kasir tidak perlu pilih format tiap kali cetak." dalam>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach(['thermal' => ['Thermal (58mm)', 'Struk kasir, kertas gulung'], 'a4' => ['A4 / A5', 'Invoice kertas biasa'], 'continuous' => ['Continuous Form', 'Kertas sambung/dot-matrix']] as $val => $label)
                                <label class="relative flex flex-col gap-1 p-4 rounded-xl border-2 cursor-pointer {{ old('format_nota', $setting->format_nota ?? 'a4') === $val ? 'border-indigo-500 bg-indigo-50' : 'border-slate-100' }}">
                                    <input type="radio" name="format_nota" value="{{ $val }}" @checked(old('format_nota', $setting->format_nota ?? 'a4') === $val) onchange="tandaiFormatNota(this)" class="absolute top-3 right-3 accent-indigo-600">
                                    <span class="text-sm font-medium text-slate-700">{{ $label[0] }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $label[1] }}</span>
                                </label>
                                @endforeach
                            </div>
                        </x-form.group>

                        <x-form.group label="Prefix Nomor Nota" name="nomor_nota_prefix" help="Contoh hasil: {{ old('nomor_nota_prefix', $setting->nomor_nota_prefix ?? 'INV') }}-20260918-001" dalam>
                            <x-form.input type="text" name="nomor_nota_prefix" value="{{ old('nomor_nota_prefix', $setting->nomor_nota_prefix ?? 'INV') }}" maxlength="20" placeholder="INV" />
                        </x-form.group>

                        <x-form.group label="Pembulatan Nota" name="pembulatan_nota" help="Hanya memengaruhi TAMPILAN nota cetak (baris 'Pembulatan' & 'Total Tagihan') - Grand Total dan seluruh pencatatan akuntansi tetap presisi asli, tidak berubah." dalam>
                            {{-- SENGAJA pakai array List [value, label] per baris, BUKAN array
                                 asosiatif ('100' => ..., dst) - PHP otomatis mengubah kunci array
                                 yang berupa string angka murni ('100','500','1000') jadi int,
                                 sehingga perbandingan === dengan nilai string dari database
                                 selalu gagal (tidak ada opsi yang pernah kecentang). --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @foreach([['tidak', 'Tidak Dibulatkan'], ['100', 'Ke Rp 100'], ['500', 'Ke Rp 500'], ['1000', 'Ke Rp 1.000']] as [$val, $label])
                                <label class="relative flex items-center justify-center p-3 rounded-xl border-2 cursor-pointer text-center {{ old('pembulatan_nota', $setting->pembulatan_nota ?? 'tidak') === $val ? 'border-indigo-500 bg-indigo-50' : 'border-slate-100' }}">
                                    <input type="radio" name="pembulatan_nota" value="{{ $val }}" @checked(old('pembulatan_nota', $setting->pembulatan_nota ?? 'tidak') === $val) onchange="tandaiPembulatanNota(this)" class="absolute inset-0 opacity-0 cursor-pointer">
                                    <span class="text-xs font-medium text-slate-700">{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                        </x-form.group>

                        <x-form.group label="Footer Nota" help="Ditampilkan di bagian bawah nota - rekening pembayaran, syarat & ketentuan, dst. Kosongkan untuk memakai pesan default." dalam>
                            {{-- Editor ringan (contenteditable + execCommand) - bukan
                                 library WYSIWYG pihak ketiga, cukup untuk kebutuhan
                                 tebal/miring/garis bawah/daftar. HTML yang tersimpan
                                 disaring ketat di server (whitelist tag, SEMUA atribut
                                 dibuang - lihat SettingAplikasiController::sanitizeFooterHtml())
                                 sebelum dipakai lagi sebagai HTML mentah di nota. --}}
                            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                                <div class="flex items-center gap-0.5 border-b border-slate-100 bg-slate-50 px-2 py-1.5">
                                    <button type="button" onmousedown="event.preventDefault()" onclick="formatFooterNota('bold')" class="w-7 h-7 rounded hover:bg-slate-200 text-slate-600 text-xs" title="Tebal"><i class="fas fa-bold"></i></button>
                                    <button type="button" onmousedown="event.preventDefault()" onclick="formatFooterNota('italic')" class="w-7 h-7 rounded hover:bg-slate-200 text-slate-600 text-xs" title="Miring"><i class="fas fa-italic"></i></button>
                                    <button type="button" onmousedown="event.preventDefault()" onclick="formatFooterNota('underline')" class="w-7 h-7 rounded hover:bg-slate-200 text-slate-600 text-xs" title="Garis Bawah"><i class="fas fa-underline"></i></button>
                                    <span class="w-px h-5 bg-slate-200 mx-1"></span>
                                    <button type="button" onmousedown="event.preventDefault()" onclick="formatFooterNota('insertUnorderedList')" class="w-7 h-7 rounded hover:bg-slate-200 text-slate-600 text-xs" title="Daftar Berpoin"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" onmousedown="event.preventDefault()" onclick="formatFooterNota('insertOrderedList')" class="w-7 h-7 rounded hover:bg-slate-200 text-slate-600 text-xs" title="Daftar Bernomor"><i class="fas fa-list-ol"></i></button>
                                    <span class="w-px h-5 bg-slate-200 mx-1"></span>
                                    <button type="button" onmousedown="event.preventDefault()" onclick="formatFooterNota('removeFormat')" class="w-7 h-7 rounded hover:bg-slate-200 text-slate-600 text-xs" title="Hapus Format"><i class="fas fa-eraser"></i></button>
                                </div>
                                <div id="editor-footer-nota" contenteditable="true" oninput="syncFooterNotaHidden()" class="min-h-[120px] p-3 text-sm text-slate-700 focus:outline-none" style="white-space: pre-wrap;">{!! \App\Support\HtmlBersih::bersihkan(old('nota_footer_text', $setting->nota_footer_text)) !!}</div>
                            </div>
                            <input type="hidden" name="nota_footer_text" id="input-footer-nota-hidden" value="{{ old('nota_footer_text', $setting->nota_footer_text) }}">
                            @error('nota_footer_text')
                                <p class="mt-1.5 ml-2 text-xs font-medium text-rose-500">{{ $message }}</p>
                            @enderror
                        </x-form.group>

                        <div class="pt-4 flex justify-end border-t border-slate-100">
                            <x-button type="submit" class="!px-10">Simpan Pengaturan</x-button>
                        </div>
                    </x-card>
                </div>

                {{-- PREVIEW LIVE - <iframe> ke SettingAplikasiController::previewNota(),
                     merender view kasir.print_nota YANG SAMA PERSIS dengan
                     cetak sungguhan (WYSIWYG asli, bukan mockup manual yang
                     bisa nyimpang dari tampilan asli seiring waktu). Lebar
                     iframe diset ke UKURAN NYATA kertas (58mm/480px/190mm,
                     sama seperti partials/print-style.blade.php), lalu
                     di-scale-down lewat CSS transform supaya proporsi antar
                     format ikut terlihat akurat, bukan cuma "kira-kira mirip". --}}
                <div class="w-full lg:w-[42%] lg:sticky lg:top-6">
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 text-center">Preview Nota - Ukuran Nyata</p>
                    <div class="bg-slate-200 rounded-2xl p-4 overflow-hidden" style="max-height: 70vh; overflow-y: auto;">
                        <div id="preview-nota-wrap" class="mx-auto bg-white shadow-md" style="transform-origin: top center;">
                            <iframe id="preview-nota-iframe" class="w-full border-0 block" style="min-height: 400px;"></iframe>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 text-center mt-2">Lebar sesuai ukuran kertas sungguhan (di-skala biar muat panel ini) - isi contoh, bukan data transaksi asli.</p>
                </div>
            </div>
        </div>

        {{-- TAB KAS --}}
        @if(str_starts_with((string) (optional(auth()->user()?->role)->nama_role ?? ''), 'Administrator'))
        <div id="tab-panel-kas" class="tab-panel-pengaturan hidden">
            <x-card class="space-y-6">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                    <span class="block text-sm font-medium text-slate-700">Hari libur &amp; jam kerja kas</span>
                    <span class="block text-xs text-slate-400 mt-0.5 mb-3">Hari tutup dan jam buka kini diatur per hari (Senin-Minggu) di halaman Jam Kerja.</span>
                    <a href="{{ route('jam-kerja.index') }}" class="inline-flex px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700"><i class="fas fa-clock mr-2"></i> Atur Jam Kerja</a>
                </div>

                <div class="pt-4 flex justify-end border-t border-slate-100">
                    <x-button type="submit" class="!px-10">Simpan Pengaturan</x-button>
                </div>
            </x-card>
        </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
    ['opt-logo-ukuran', 'opt-logo-gaya'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', () => {
            const p = document.getElementById('pratinjau-logo');
            if (!p) return;
            p.className = 'sidebar-logo logo-' + document.getElementById('opt-logo-ukuran').value + ' logo-gaya-' + document.getElementById('opt-logo-gaya').value;
        });
    });

    function previewLogoAplikasi(input) {
        if (!input.files || !input.files[0]) return;
        const img = document.getElementById('preview-logo');
        const placeholder = document.getElementById('preview-logo-placeholder');
        img.src = URL.createObjectURL(input.files[0]);
        const pratinjau = document.getElementById("pratinjau-logo-img");
        if (pratinjau) pratinjau.src = img.src;
        img.classList.remove('hidden');
        if (placeholder) placeholder.classList.add('hidden');
        perbaruiPreviewNota();
    }

    function pindahTabPengaturan(nama) {
        document.querySelectorAll('.tab-panel-pengaturan').forEach(el => {
            el.classList.toggle('hidden', el.id !== `tab-panel-${nama}`);
        });
        document.querySelectorAll('.tab-btn-pengaturan').forEach(btn => {
            const aktif = btn.id === `tab-btn-${nama}`;
            btn.classList.toggle('border-indigo-600', aktif);
            btn.classList.toggle('text-indigo-600', aktif);
            btn.classList.toggle('border-transparent', !aktif);
            btn.classList.toggle('text-slate-400', !aktif);
        });
        if (nama === 'nota') perbaruiPreviewNota();
    }

    function tandaiFormatNota(radio) {
        radio.closest('.grid').querySelectorAll('label').forEach(l => {
            l.classList.remove('border-indigo-500', 'bg-indigo-50');
            l.classList.add('border-slate-100');
        });
        const label = radio.closest('label');
        label.classList.remove('border-slate-100');
        label.classList.add('border-indigo-500', 'bg-indigo-50');
        perbaruiPreviewNota();
    }

    function tandaiPembulatanNota(radio) {
        radio.closest('.grid').querySelectorAll('label').forEach(l => {
            l.classList.remove('border-indigo-500', 'bg-indigo-50');
            l.classList.add('border-slate-100');
        });
        const label = radio.closest('label');
        label.classList.remove('border-slate-100');
        label.classList.add('border-indigo-500', 'bg-indigo-50');
        perbaruiPreviewNota();
    }

    // Toolbar formatting pakai document.execCommand - API lama/deprecated tapi
    // masih berfungsi luas di browser berbasis Chromium untuk perintah dasar
    // (bold/italic/underline/list) - cukup untuk kebutuhan footer nota tanpa
    // perlu menambah library WYSIWYG pihak ketiga. HTML hasilnya DISARING
    // KETAT di server sebelum disimpan (lihat SettingAplikasiController).
    function formatFooterNota(command) {
        document.getElementById('editor-footer-nota').focus();
        document.execCommand(command, false, null);
        syncFooterNotaHidden();
    }

    function syncFooterNotaHidden() {
        const editor = document.getElementById('editor-footer-nota');
        document.getElementById('input-footer-nota-hidden').value = editor.innerHTML;
        perbaruiPreviewNota();
    }

    // Lebar NYATA per format (mm dikonversi ke px 96dpi) - HARUS sama persis
    // dengan partials/print-style.blade.php (.sheet max-width) supaya
    // preview benar-benar WYSIWYG, bukan kira-kira.
    const LEBAR_MM_KE_PX = { thermal: 58 * 3.7795, a4: 480, continuous: 190 * 3.7795 };
    const LEBAR_PANEL_MAKS = 340; // px - lebar visual panel preview di sisi kanan

    let timerPreviewNota = null;
    function perbaruiPreviewNota() {
        clearTimeout(timerPreviewNota);
        timerPreviewNota = setTimeout(jalankanPreviewNota, 300);
    }

    function jalankanPreviewNota() {
        const prefix = document.querySelector('input[name="nomor_nota_prefix"]')?.value || 'INV';
        const format = document.querySelector('input[name="format_nota"]:checked')?.value || 'a4';
        const pembulatan = document.querySelector('input[name="pembulatan_nota"]:checked')?.value || 'tidak';
        const footerHtml = document.getElementById('editor-footer-nota')?.innerHTML.trim() || '';

        const lebarAsli = LEBAR_MM_KE_PX[format] || LEBAR_MM_KE_PX.a4;
        const skala = Math.min(1, LEBAR_PANEL_MAKS / lebarAsli);

        const wrap = document.getElementById('preview-nota-wrap');
        const iframe = document.getElementById('preview-nota-iframe');
        wrap.style.width = lebarAsli + 'px';
        wrap.style.transform = `scale(${skala})`;
        iframe.style.width = lebarAsli + 'px';

        const params = new URLSearchParams({ format, prefix, pembulatan, footer: footerHtml });
        iframe.src = `{{ route('pengaturan-aplikasi.preview-nota') }}?${params.toString()}`;
    }

    // Tinggi iframe & wrapper (yang sudah di-scale) disesuaikan ke tinggi
    // KONTEN ASLI setelah preview selesai dimuat - supaya tidak ada spasi
    // kosong berlebih di bawah ATAU konten yang terpotong, apa pun panjang
    // isi contoh notanya.
    document.getElementById('preview-nota-iframe').addEventListener('load', function () {
        try {
            const tinggiAsli = this.contentDocument.body.scrollHeight;
            this.style.height = tinggiAsli + 'px';
            const wrap = document.getElementById('preview-nota-wrap');
            const skalaSaatIni = parseFloat(wrap.style.transform.replace(/[^0-9.]/g, '')) || 1;
            wrap.style.height = (tinggiAsli * skalaSaatIni) + 'px';
        } catch (e) { /* cross-origin dsb - preview tetap tampil, cuma tinggi wrapper tidak auto-pas */ }
    });

    document.getElementById('form-pengaturan-aplikasi').addEventListener('input', perbaruiPreviewNota);
    document.addEventListener('DOMContentLoaded', () => {
        syncFooterNotaHidden();
        perbaruiPreviewNota();
    });
</script>
@endpush
@endsection
