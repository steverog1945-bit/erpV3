@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Kategori Kas Kecil" subtitle="Daftar kategori pemasukan &amp; pengeluaran kas kecil - dipakai sebagai pilihan tetap saat mencatat">
        <x-slot:actions>
            <x-button onclick="openTambahModalKategoriKasKecil()">
                <i class="fas fa-tags"></i>
                Kategori Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('kategori-kas-kecil.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Kategori</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama kategori...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('kategori-kas-kecil.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">Nama Kategori</th>
                <th class="px-4 py-5 text-left">Jenis</th>
                <th class="px-6 py-5 text-left">Akun Terkait</th>
                <th class="px-8 py-5 text-right">Manajemen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($kategori as $i => $kat)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $kategori->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <div class="text-[14px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors">
                        {{ $kat->nama_kategori }}
                    </div>
                </td>
                <td class="px-4 py-5">
                    <span class="text-[10px] font-medium uppercase rounded px-2 py-1 {{ $kat->jenis === 'Masuk' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-500 bg-rose-50' }}">{{ $kat->jenis === 'Masuk' ? 'Pemasukan' : 'Pengeluaran' }}</span>
                </td>
                <td class="px-6 py-5">
                    @if($kat->kode_akun)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                            {{ $kat->kode_akun }} &middot; {{ optional($akunBeban->firstWhere('kode_akun', $kat->kode_akun))->nama_akun ?? '-' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-medium bg-amber-50 text-amber-600 border border-amber-100 uppercase">
                            <i class="fas fa-exclamation-triangle"></i> Belum Dipetakan
                        </span>
                    @endif
                    @if($kat->dibayar_pemilik)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-medium bg-sky-50 text-sky-700 border border-sky-100 ml-1" title="Dicatat tanpa mengurangi saldo Kas Kecil">Dibayar pemilik</span>
                    @endif
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick="editKategoriKasKecil({{ json_encode($kat) }})"
                            class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-xl transition-all" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </button>

                        <form id="delete-form-kategori-kas-kecil-{{ $kat->id_kategori_kas_kecil }}" action="{{ route('kategori-kas-kecil.destroy', $kat->id_kategori_kas_kecil) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDeleteKategoriKasKecil('{{ $kat->id_kategori_kas_kecil }}', {{ json_encode($kat->nama_kategori) }})"
                            class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-xl transition-all">
                            <i class="far fa-trash-alt text-sm"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="py-24 text-center">
                    <div class="flex flex-col items-center">
                        <i class="fas fa-folder-open text-slate-100 text-6xl mb-4"></i>
                        <p class="text-slate-500 font-medium text-sm">Belum ada kategori yang terdaftar.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$kategori" />
</div>

{{-- MODAL CRUD (Unified)
     Sama seperti halaman Kategori: dulu dibuka lewat
     classList.remove('hidden') + body.style.overflow inline, sehingga
     tidak terlihat (data-state tak pernah "open") dan halaman tidak
     bisa digulir lagi setelah ditutup. Sekarang lewat <x-modal>. --}}
<x-modal id="kategori-kas-kecil" title="Tambah Kategori" maxWidth="md">
    <form id="formKategoriKasKecil" action="{{ route('kategori-kas-kecil.store') }}" method="POST" class="space-y-4">
        @csrf
        <div id="methodFieldKategoriKasKecil"></div>

        <x-form.group label="Nama Kategori" name="nama_kategori" required dalam>
            <x-form.input type="text" name="nama_kategori" id="inputNamaKategoriKasKecil"
                         placeholder="Contoh: Beban Internet" />
        </x-form.group>

        <x-form.group label="Jenis" name="jenis" help="Menentukan akun mana yang boleh dipilih di bawah." dalam>
            <x-form.select name="jenis" id="inputJenisKategoriKasKecil" onchange="saringAkunKategoriKasKecil()">
                <option value="Keluar">Pengeluaran</option>
                <option value="Masuk">Pemasukan</option>
            </x-form.select>
        </x-form.group>

        <x-form.group label="Akun Terkait" name="kode_akun"
                      help="Pengeluaran dipetakan ke akun Beban, pemasukan ke akun Pendapatan atau Modal. Nomornya otomatis masuk jurnal dan Laba Rugi setiap kali kategori ini dipakai di Kas Kecil." dalam>
            <x-form.select name="kode_akun" id="inputKodeAkunKategoriKasKecil" required>
                <option value="">-- Pilih akun --</option>
                @foreach($akunBeban as $akun)
                    <option value="{{ $akun->kode_akun }}" data-jenis="{{ $akun->tipe_akun === 'Beban' ? 'Keluar' : 'Masuk' }}">{{ $akun->kode_akun }} - {{ $akun->nama_akun }}</option>
                @endforeach
            </x-form.select>
        </x-form.group>

        <label id="barisDibayarPemilik" class="flex items-start gap-3 text-sm text-slate-600 cursor-pointer">
            <input type="checkbox" name="dibayar_pemilik" value="1" id="inputDibayarPemilikKategoriKasKecil" class="mt-1 accent-indigo-600">
            <span>Dibayar langsung pemilik<br><small class="text-slate-400">Tetap dicatat sebagai biaya, tetapi tidak mengurangi saldo Kas Kecil.</small></span>
        </label>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('kategori-kas-kecil')">Batal</x-button>
            <x-button type="submit">Simpan Kategori</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<script>
    const formKategoriKasKecil = document.getElementById('formKategoriKasKecil');

    // Tambah dan edit memakai form yang sama, jadi pengisian field-nya
    // dikumpulkan di satu tempat. Bedanya cuma judul, alamat aksi, dan
    // field yang perlu diisi. Warna header tidak lagi berubah-ubah:
    // menambahkan dan mengubah bukan dua jenis kejadian yang berbeda.
    function isiFormKategoriKasKecil(o) {
        document.getElementById('modal-kategori-kas-kecil-title').innerText = o.judul;
        formKategoriKasKecil.action = o.aksi;
        document.getElementById('methodFieldKategoriKasKecil').innerHTML = o.metode;
        document.getElementById('inputNamaKategoriKasKecil').value = o.nama || '';
        document.getElementById('inputJenisKategoriKasKecil').value = o.jenis || 'Keluar';
        saringAkunKategoriKasKecil();
        document.getElementById('inputKodeAkunKategoriKasKecil').value = o.akun || '';
        document.getElementById('inputDibayarPemilikKategoriKasKecil').checked = !!o.pemilik;
    }

    function openTambahModalKategoriKasKecil() {
        isiFormKategoriKasKecil({
            judul: 'Tambah Kategori',
            aksi: '{{ route('kategori-kas-kecil.store') }}',
            metode: '',
        });
        openModal('kategori-kas-kecil');
    }

    function editKategoriKasKecil(data) {
        isiFormKategoriKasKecil({
            judul: 'Edit Kategori',
            aksi: `{{ url('/kategori-kas-kecil') }}/${data.id_kategori_kas_kecil}`,
            metode: '@method("PUT")',
            nama: data.nama_kategori,
            jenis: data.jenis,
            akun: data.kode_akun,
            pemilik: data.dibayar_pemilik,
        });
        openModal('kategori-kas-kecil');
    }

    // Pilihan akun mengikuti jenis: pengeluaran -> Beban, pemasukan -> Pendapatan/Modal.
    function saringAkunKategoriKasKecil() {
        const jenis = document.getElementById('inputJenisKategoriKasKecil').value;
        const sel = document.getElementById('inputKodeAkunKategoriKasKecil');
        [...sel.options].forEach(o => { if (o.value) { const cocok = o.dataset.jenis === jenis; o.hidden = !cocok; o.disabled = !cocok; } });
        if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) sel.value = '';
        // Hanya pengeluaran yang bisa dibayar langsung pemilik.
        document.getElementById('barisDibayarPemilik').hidden = jenis !== 'Keluar';
    }

    function confirmDeleteKategoriKasKecil(id, nama) {
        Swal.fire({
            title: 'Hapus Kategori?',
            html: `Kategori <b>${escHtml(nama)}</b> akan dihapus permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F5F8FF',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(`delete-form-kategori-kas-kecil-${id}`).submit();
            }
        });
    }
</script>
@endsection
