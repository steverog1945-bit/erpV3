@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Setoran Konsumen" subtitle="Pencatatan setoran deposit pelanggan.">
        <x-slot:actions>
            <x-button onclick="toggleModal('deposit-tambah')">
                <i class="fas fa-piggy-bank"></i>
                Setoran Baru
            </x-button>
            @if(optional(auth()->user()->role)->nama_role === 'Administrator')
            <x-button variant="secondary" onclick="toggleModal('deposit-kembali')">
                <i class="fas fa-hand-holding-dollar"></i>
                Kembalikan Uang
            </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- SALDO PER KONSUMEN --}}
    <x-card :padding="false" class="mb-8">
        <div class="px-5 py-4 border-b border-slate-200">
            {{-- Judul kartu: tanpa huruf besar dan tanpa tracking. Di
                 OpenCode hierarki datang dari ukuran dan warna, bukan dari
                 text-transform. --}}
            <h3 class="text-sm font-medium text-slate-900">Saldo Deposit Aktif</h3>
        </div>
        <x-table bare>
            <thead>
                <tr>
                    <th class="text-left">Konsumen</th>
                    <th class="text-center">Golongan</th>
                    <th class="text-right">Saldo Deposit</th>
                </tr>
            </thead>
            <tbody>
                    @forelse($saldoPerKonsumen as $s)
                    {{-- Baris saldo 0 (deposit pernah ada, sekarang sudah habis
                         terpakai semua) SENGAJA tetap ditampilkan, bukan
                         dibuang dari daftar - lihat catatan di
                         KonsumenDepositController::index(). Diberi opacity
                         lebih pudar + badge "Habis Terpakai" supaya beda
                         jelas dari yang masih aktif punya saldo. --}}
                    <tr class="{{ $s->saldo_deposit <= 0 ? 'opacity-60' : '' }}">
                        <td class="text-sm font-medium text-slate-700">{{ $s->nama_konsumen }}</td>
                        <td class="text-center">
                            <span class="badge bg-slate-100 text-slate-600">
                                {{ $s->nama_golongan ?? '-' }}
                            </span>
                        </td>
                        <td class="text-right">
                            @if($s->saldo_deposit > 0)
                                <span class="text-sm font-medium text-emerald-600">Rp {{ number_format($s->saldo_deposit, 0, ',', '.') }}</span>
                            @else
                                <span class="badge bg-slate-100 text-slate-400" title="Deposit konsumen ini sudah habis terpakai semua - tidak bisa dipakai lagi untuk pembayaran sampai ada setoran baru.">
                                    <i class="fas fa-ban"></i> Habis Terpakai
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="py-16 text-center text-slate-400 font-medium italic">Belum ada deposit tercatat.</td>
                    </tr>
                    @endforelse
            </tbody>
        </x-table>
    </x-card>

    {{-- RIWAYAT SETORAN --}}
    <x-card :padding="false">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="text-sm font-medium text-slate-900">Riwayat Deposit (50 Terbaru)</h3>
        </div>
        <x-table bare>
            <thead>
                <tr>
                    <th class="text-left">Tanggal</th>
                    <th class="text-left">Konsumen</th>
                    <th class="text-center">Tipe</th>
                    <th class="text-right">Nominal</th>
                    <th class="text-center">Metode / Nota</th>
                    <th class="text-left">Keterangan</th>
                    <th class="text-center">Dicatat Oleh</th>
                </tr>
            </thead>
            <tbody>
                    @forelse($riwayat as $r)
                    <tr>
                        <td class="text-sm text-slate-600 font-medium">{{ \Carbon\Carbon::parse($r->tgl_deposit)->translatedFormat('d M Y') }}</td>
                        <td class="text-sm font-medium text-slate-700">{{ $r->nama_konsumen }}</td>
                        <td class="text-center">
                            <span class="badge {{ $r->tipe === 'Setoran' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                                {{ $r->tipe }}
                            </span>
                        </td>
                        <td class="text-right">
                            <span class="text-sm font-medium {{ $r->tipe === 'Setoran' ? 'text-emerald-600' : 'text-rose-500' }}">
                                {{ $r->tipe === 'Setoran' ? '+' : '-' }} Rp {{ number_format($r->nominal, 0, ',', '.') }}
                            </span>
                            {{-- Deposit dicatat sebagai saldo terpool per konsumen
                                 (bukan dialokasikan per-setoran/FIFO ke transaksi
                                 tertentu) - badge ini menunjukkan status SALDO
                                 KONSUMEN SAAT INI, bukan klaim persis setoran ini
                                 sudah/belum kepakai transaksi mana. --}}
                            @if($r->tipe === 'Setoran')
                                @php $saldoSaatIni = $saldoMap[$r->id_konsumen]->saldo_deposit ?? 0; @endphp
                                <div class="mt-1">
                                    @if($saldoSaatIni <= 0)
                                        <span class="badge bg-slate-100 text-slate-400" title="Saldo deposit konsumen ini sekarang habis - setoran ini (dan setoran lain miliknya) sudah terpakai semua.">
                                            <i class="fas fa-ban"></i> Habis Terpakai
                                        </span>
                                    @elseif($saldoSaatIni < $r->nominal)
                                        <span class="badge bg-amber-50 text-amber-600" title="Saldo deposit konsumen ini sekarang lebih kecil dari nominal setoran ini - sebagian sudah terpakai.">
                                            <i class="fas fa-adjust"></i> Terpakai Sebagian
                                        </span>
                                    @else
                                        <span class="badge bg-emerald-50 text-emerald-600">
                                            <i class="fas fa-check-circle"></i> Tersedia
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($r->tipe === 'Setoran')
                                <span class="badge {{ $r->metode_bayar === 'Tunai' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' }}">
                                    {{ $r->metode_bayar }}
                                </span>
                            @else
                                <span class="text-indigo-500">{{ $r->nomor_nota ?? '-' }}</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-500 italic truncate max-w-xs">{{ $r->keterangan ?? '-' }}</td>
                        <td class="text-center text-slate-400">{{ $r->dicatat_oleh ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center text-slate-400 font-medium italic">Belum ada riwayat deposit.</td>
                    </tr>
                    @endforelse
            </tbody>
        </x-table>
    </x-card>
</div>

{{-- MODAL SETORAN BARU

     Tanpa py-10 sendiri (p-5 sudah ada di <x-modal>) dan tanpa
     max-w + mx-auto yang membuat isinya bergeser ke tengah.
--}}
<x-modal id="deposit-tambah" title="Tambah Setoran Konsumen"
         subtitle="Dana muka yang mengurangi tagihan nota"
         icon="fa-money-bill-transfer" maxWidth="md">
<form action="{{ route('konsumen-deposit.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="ld">
                    <select id="ld_id_konsumen_0" name="id_konsumen" required
                            class="w-full py-3.5 text-sm font-medium text-slate-700 appearance-none">
                        <option value="">-- Pilih Konsumen --</option>
                        @foreach($konsumen as $k)
                            <option value="{{ $k->id_konsumen }}">{{ $k->nama_konsumen }}</option>
                        @endforeach
                    </select>
                    <label class="ld-label" for="ld_id_konsumen_0">Konsumen<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <input id="ld_nominal_1" type="text" inputmode="numeric" autocomplete="off" name="nominal" placeholder="0" required
                           class="w-full py-3.5 text-sm text-slate-700 format-ribuan">
                    <label class="ld-label" for="ld_nominal_1">Nominal Deposit<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="ld">
                        <input placeholder=" " id="ld_tgl_deposit_2" type="date" name="tgl_deposit" value="{{ date('Y-m-d') }}" required
                               class="w-full py-3.5 text-sm font-medium text-slate-700">
                        <label class="ld-label" for="ld_tgl_deposit_2">Tanggal<span class="ld-wajib" aria-hidden="true">*</span></label>
                    </div>
                    <div class="ld">
                        <select id="ld_metode_bayar_3" name="metode_bayar" required
                                class="w-full py-3.5 text-sm font-medium text-slate-700 appearance-none">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                        </select>
                        <label class="ld-label" for="ld_metode_bayar_3">Metode<span class="ld-wajib" aria-hidden="true">*</span></label>
                    </div>
                </div>
                <div class="ld">
                    <textarea id="ld_keterangan_4" name="keterangan" placeholder="Keterangan setoran (opsional)..."
                              class="w-full py-3.5 text-sm font-medium text-slate-700"></textarea>
                    <label class="ld-label" for="ld_keterangan_4">Keterangan</label>
                </div>

                <x-slot:footer>
                    <x-button type="button" variant="secondary" onclick="toggleModal('deposit-tambah')">Batal</x-button>
                    <x-button type="submit">Simpan Setoran</x-button>
                </x-slot:footer>
            </form>
</x-modal>
@if(optional(auth()->user()->role)->nama_role === 'Administrator')
<x-modal id="deposit-kembali" title="Kembalikan Uang Deposit"
         subtitle="Uang keluar dari kas dan mengurangi saldo pelanggan"
         icon="fa-hand-holding-dollar" maxWidth="md">
<form action="{{ route('konsumen-deposit.kembalikan') }}" method="POST" class="space-y-4">
    @csrf
    <div class="ld">
        <select id="ld_id_konsumen_5" name="id_konsumen" required class="w-full py-3.5 text-sm font-medium text-slate-700 appearance-none">
            <option value="">-- Pilih Konsumen --</option>
            @foreach($saldoPerKonsumen->where('saldo_deposit', '>', 0) as $s)
                <option value="{{ $s->id_konsumen }}">{{ $s->nama_konsumen }} (saldo Rp {{ number_format($s->saldo_deposit, 0, ',', '.') }})</option>
            @endforeach
        </select>
        <label class="ld-label" for="ld_id_konsumen_5">Konsumen<span class="ld-wajib" aria-hidden="true">*</span></label>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div class="ld">
            <input id="ld_nominal_6" type="text" inputmode="numeric" autocomplete="off" name="nominal" placeholder="0" required class="w-full py-3.5 text-sm text-slate-700 format-ribuan">
            <label class="ld-label" for="ld_nominal_6">Nominal<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>
        <div class="ld">
            <select id="ld_metode_bayar_7" name="metode_bayar" required class="w-full py-3.5 text-sm font-medium text-slate-700 appearance-none">
                <option value="Tunai">Tunai</option>
                <option value="Transfer">Transfer</option>
            </select>
            <label class="ld-label" for="ld_metode_bayar_7">Dibayarkan lewat<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>
    </div>
    <div class="ld">
        <textarea id="ld_alasan_8" name="alasan" required minlength="5" maxlength="500" placeholder="Mis. nota dibatalkan, pelanggan minta uangnya kembali" class="w-full py-3.5 text-sm font-medium text-slate-700"></textarea>
        <label class="ld-label" for="ld_alasan_8">Alasan<span class="ld-wajib" aria-hidden="true">*</span></label>
    </div>
    <x-slot:footer>
        <x-button type="button" variant="secondary" onclick="toggleModal('deposit-kembali')">Batal</x-button>
        <x-button type="submit">Kembalikan Uang</x-button>
    </x-slot:footer>
</form>
</x-modal>
@endif
<script>
</script>
@endsection
