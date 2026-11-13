@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Rekening Bank Perusahaan" subtitle="Daftar rekening bank untuk penerimaan pembayaran nota.">
        <x-slot:actions>
            <x-button onclick="toggleModal('bank-tambah')">
                <i class="fas fa-plus-circle"></i>
                Tambah Rekening
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('bank.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64 lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Rekening</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama bank, atas nama, atau no. rekening...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('bank.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- Gambar QRIS perusahaan: dipakai halaman Input Pembayaran saat metode
         QRIS dipilih. Tersimpan sebagai berkas di storage/app/public/qris/
         (tanpa kolom database) - mengunggah ulang menggantikan gambar lama. --}}
    @php
        $qrisAda = collect(['png', 'jpg', 'jpeg', 'webp'])->first(
            fn ($e) => file_exists(public_path('storage/qris/qris.' . $e))
        );
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center gap-5">
            <div class="flex items-center justify-center shrink-0 w-32 h-32 rounded-2xl border border-slate-200 bg-slate-50/60 overflow-hidden">
                @if($qrisAda)
                    <img src="{{ asset('storage/qris/qris.' . $qrisAda) }}" alt="QRIS saat ini" class="w-full h-full object-contain" loading="lazy">
                @else
                    <i class="fas fa-qrcode text-3xl text-slate-300"></i>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800">Gambar QRIS Perusahaan</p>
                <p class="text-[11px] text-slate-400 mt-1 max-w-xl">Ditampilkan di halaman Input Pembayaran saat kasir memilih metode <b>QRIS</b>, supaya konsumen bisa memindai langsung dari layar kasir. PNG/JPG/WEBP maks 2 MB. Mengunggah gambar baru menggantikan yang lama.</p>
                <form method="POST" action="{{ route('bank.qris.update') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="qris" accept="image/png,image/jpeg,image/webp" required
                           class="text-xs font-medium text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-semibold hover:file:bg-indigo-100">
                    <button type="submit" class="h-[38px] px-5 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700 transition-colors">Unggah QRIS</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Kartu rekening bergaya kartu ATM: gradien gelap + chip + teks besar
         yang mudah dibaca dari jauh. Tambah/hapus tetap lewat form; nomor
         rekening disalin lewat clipboard (tombol SALIN). --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @forelse($bank as $b)
    @php
        // Palet gelap per nama bank (CRC32) - semua gradien gelap => teks putih
        // selalu kontras & terbaca. nomor diberi spasi per 4 digit.
        $palettes = [
            'linear-gradient(135deg,#0B2440 0%,#12365B 55%,#1D4E7C 100%)',
            'linear-gradient(135deg,#102A43 0%,#1F3B57 55%,#2E4E68 100%)',
            'linear-gradient(135deg,#0D2B26 0%,#134E4A 55%,#1F5F57 100%)',
            'linear-gradient(135deg,#241536 0%,#3B1F56 55%,#4E2A68 100%)',
            'linear-gradient(135deg,#2B1414 0%,#4A1F1F 55%,#5E2A2A 100%)',
            'linear-gradient(135deg,#101426 0%,#1E293B 55%,#2B3648 100%)',
        ];
        $palette = $palettes[crc32((string) $b->nama_bank) % count($palettes)];
        // Format nomor rekening 3-3-4 / 4-4-4 / apa pun: beri spasi setiap 4 digit dari KIRI-SUMBER,
        // biar mudah dibaca kasir tanpa mengubah isinya.
        $norekTampil = trim(preg_replace('/(.{4})(?=.)/', '$1 ', (string) $b->nomor_rekening));
    @endphp
    <div class="bank-atm relative overflow-hidden rounded-2xl text-white select-none aspect-[1.586/1] max-w-[440px] w-full shadow-[0_10px_32px_-8px_rgba(2,6,23,.45)]"
         style="background: {{ $palette }}">
        {{-- Kilau diagonal halus (tidak mengaburkan teks) --}}
        <div class="absolute -top-24 -right-20 w-72 h-72 rounded-full opacity-25 pointer-events-none" style="background: radial-gradient(circle, #ffffff 0%, transparent 70%)"></div>
        <div class="absolute -bottom-28 -left-16 w-64 h-64 rounded-full opacity-10 pointer-events-none" style="background: radial-gradient(circle, #ffffff 0%, transparent 70%)"></div>

        <div class="relative h-full flex flex-col justify-between p-5 sm:p-6">
            {{-- Baris atas: nama bank + logo "kartu" (chip & contactless) --}}
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[9px] font-bold uppercase tracking-[.28em] text-white/55">Bank Perusahaan</p>
                    <p class="mt-0.5 text-lg sm:text-xl font-extrabold uppercase tracking-wide text-white truncate">{{ $b->nama_bank }}</p>
                </div>
                <svg width="38" height="28" viewBox="0 0 38 28" aria-hidden="true" class="shrink-0 mt-0.5">
                    <rect x="1" y="1" width="36" height="26" rx="5" fill="#E5C469" stroke="#B98A2F" stroke-width="1.5"/>
                    <rect x="1" y="9" width="36" height="10" fill="#C9A227"/>
                    <line x1="9" y1="1" x2="9" y2="27" stroke="#8A6B14" stroke-width="1.2"/>
                    <line x1="29" y1="1" x2="29" y2="27" stroke="#8A6B14" stroke-width="1.2"/>
                    <rect x="14" y="9" width="10" height="10" fill="none" stroke="#8A6B14" stroke-width="1.1"/>
                </svg>
            </div>

            {{-- Baris tengah: nomor rekening besar & jelas --}}
            <p class="font-mono font-semibold text-[20px] sm:text-[23px] leading-none tracking-[.22em] text-white" style="text-shadow: 0 2px 6px rgba(0,0,0,.35)">{{ $norekTampil }}</p>

            {{-- Baris bawah: pemilik + aksi (Salin/Ubah/Hapus) --}}
            <div class="flex items-end justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[8.5px] font-bold uppercase tracking-[.22em] text-white/50">Pemilik Rekening</p>
                    <p class="mt-0.5 text-[11px] sm:text-[12px] font-bold uppercase tracking-wider text-white/95 truncate">a/n {{ $b->atas_nama }}</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" data-salin="{{ $b->nomor_rekening }}" class="salin-norek inline-flex items-center gap-1 h-8 px-2.5 rounded-lg bg-white/15 hover:bg-white/25 border border-white/25 text-white text-[9.5px] font-bold uppercase tracking-widest transition-colors">
                        <i class="far fa-copy text-[10px]"></i> Salin
                    </button>
                    <button onclick="openEditBank({{ json_encode($b) }})"
                            class="w-8 h-8 flex items-center justify-center text-white/85 hover:text-white hover:bg-white/15 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                        <i class="far fa-edit text-[13px]"></i>
                    </button>
                    <form id="delete-form-bank-{{ $b->id_bank }}" action="{{ route('bank.destroy', $b->id_bank) }}" method="POST">
                        @csrf @method('DELETE')
                    </form>
                    <button type="button" onclick="confirmDeleteBank('{{ $b->id_bank }}', {{ json_encode($b->nama_bank . ' - ' . $b->nomor_rekening) }})"
                            class="w-8 h-8 flex items-center justify-center text-white/85 hover:text-rose-200 hover:bg-rose-500/25 rounded-lg transition-all" aria-label="Hapus" title="Hapus">
                        <i class="far fa-trash-alt text-[13px]"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="copied-toast absolute inset-x-0 bottom-0 translate-y-full opacity-0 transition-all duration-300 bg-emerald-600 text-white text-center text-[10px] font-semibold uppercase tracking-widest py-1.5">Tersalin ke clipboard</div>
    </div>
    @empty
    <div class="col-span-full py-20 text-center text-slate-400 font-medium italic">Belum ada rekening bank yang terdaftar.</div>
    @endforelse
</div>

<x-table.pagination :paginator="$bank" />

</div>

<x-modal id="bank-tambah" title="Tambah Bank"
         subtitle="Rekening bank untuk sinkronisasi pembayaran"
         icon="fa-building-columns" maxWidth="md">
<form action="{{ route('bank.store') }}" method="POST" class="relative w-full max-w-md bg-white p-8 space-y-4 shadow-2xl">
        @csrf
        <h3 class="text-xl text-slate-900 mb-2">Input Rekening Baru</h3>
        <input type="text" name="nama_bank" placeholder="Nama Bank (BCA, Mandiri, dll)" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500" required>
        <input type="text" name="nomor_rekening" placeholder="Nomor Rekening" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500" required>
        <input type="text" name="atas_nama" placeholder="Atas Nama (Pemilik)" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500" required>
        <x-button type="submit" class="w-full mt-2">Simpan Rekening</x-button>
    </form>
</x-modal>
<x-modal id="bank-edit" title="Edit Bank"
         subtitle="Perubahan berlaku untuk transaksi berikutnya"
         icon="fa-pen" maxWidth="md">
<form id="formEditBank" method="POST" class="relative w-full max-w-md bg-white p-8 space-y-4 shadow-2xl">
        @csrf
        @method('PUT')
        <h3 class="text-xl text-slate-900 mb-2">Perbarui Rekening</h3>
        
        <div>
            <label class="text-xs">Nama Bank</label>
            <input type="text" name="nama_bank" id="edit_nama_bank" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500" required>
        </div>
        
        <div>
            <label class="text-xs">Nomor Rekening</label>
            <input type="text" name="nomor_rekening" id="edit_nomor_rekening" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500" required>
        </div>
        
        <div>
            <label class="text-xs">Atas Nama</label>
            <input type="text" name="atas_nama" id="edit_atas_nama" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500" required>
        </div>

        <x-button type="submit" class="w-full mt-2">Perbarui Rekening</x-button>
    </form>
</x-modal>
<script>

    // Salin nomor rekening satu klik (clipboard API, fallback execCommand
    // untuk browser lama/teks yg tak bisa dipilih).
    document.querySelectorAll('.salin-norek').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var nomor = btn.getAttribute('data-salin') || '';
            var toast = btn.closest('.bank-card') ? btn.closest('.bank-card').querySelector('.copied-toast') : null;
            var berhasil = function () {
                if (!toast) return;
                toast.classList.remove('translate-y-full', 'opacity-0');
                setTimeout(function () { toast.classList.add('translate-y-full', 'opacity-0'); }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(nomor).then(berhasil).catch(function () {
                    var t = document.createElement('textarea');
                    t.value = nomor; t.style.cssText = 'position:fixed;opacity:0';
                    document.body.appendChild(t); t.select();
                    try { document.execCommand('copy'); } catch (_) {}
                    document.body.removeChild(t); berhasil();
                });
            } else {
                var t2 = document.createElement('textarea');
                t2.value = nomor; t2.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(t2); t2.select();
                try { document.execCommand('copy'); } catch (_) {}
                document.body.removeChild(t2); berhasil();
            }
        });
    });

    function openEditBank(data) {
        // Set Action URL sesuai ID Bank
        const form = document.getElementById('formEditBank');
        form.action = `{{ url('/bank') }}/${data.id_bank}`;
        
        // Isi nilai input modal edit
        document.getElementById('edit_nama_bank').value = data.nama_bank;
        document.getElementById('edit_nomor_rekening').value = data.nomor_rekening;
        document.getElementById('edit_atas_nama').value = data.atas_nama;
        
        // Tampilkan modal
        toggleModal('bank-edit');
    }

    function confirmDeleteBank(id, label) {
        Swal.fire({
            title: 'Hapus Rekening?',
            html: `Rekening <b>${escHtml(label)}</b> akan dihapus permanen.`,
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
                document.getElementById(`delete-form-bank-${id}`).submit();
            }
        });
    }
</script>
@endsection
