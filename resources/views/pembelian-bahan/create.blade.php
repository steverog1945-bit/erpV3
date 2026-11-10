@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="max-w-6xl mx-auto px-4 py-10">
    <form action="{{ route('pembelian-bahan.store') }}" method="POST" id="main-form">
        @csrf

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-medium text-slate-800 uppercase tracking-tight">Entry Faktur</h1>
                    <p class="text-slate-400 font-medium italic">Konversi Surat Jalan menjadi Tagihan Pembelian</p>
                </div>
            </div>
            <x-card :padding="false" class="px-8 py-4">
                <div class="text-right">
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Tagihan</p>
                    <h2 class="text-3xl font-medium text-indigo-600" id="total-header">Rp 0</h2>
                </div>
            </x-card>
        </div>

        <x-card class="mb-8">
            @if ($errors->any())
                <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-rose-500 text-white p-4 rounded-2xl mb-6 font-medium shadow-lg">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <x-form.group label="No. Surat Jalan" dalam>
                    <div class="relative group cursor-pointer" onclick="openModal('sj-picker')" role="button" tabindex="-1" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openModal('sj-picker'); }">
                        <input type="text" id="display_no_sj" readonly class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500  pr-11 cursor-pointer" placeholder="Pilih SJ...">
                        <input type="hidden" name="id_kedatangan" id="id_kedatangan">
                        <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 group-hover:text-indigo-500"></i>
                    </div>
                </x-form.group>

                <x-form.group label="Pemasok" dalam>
                    <input type="text" id="display_supplier" readonly class="w-full bg-slate-100 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-500 shadow-inner" placeholder="-">
                </x-form.group>

                <x-form.group label="Tgl Transaksi" dalam>
                    <x-form.input type="date" name="tgl_transaksi" value="{{ date('Y-m-d') }}" required />
                </x-form.group>

                <x-form.group label="No. Faktur Vendor" dalam>
                    <x-form.input type="text" name="no_faktur" required placeholder="Contoh: INV/9910" />
                </x-form.group>

                <div id="info-online-faktur" class="hidden md:col-span-4 rounded-2xl bg-orange-50 border border-orange-100 px-5 py-3 text-sm font-medium text-orange-700"></div>
                <x-form.group label="Ongkir (Rp)" dalam><x-form.input type="text" inputmode="numeric" name="ongkir" id="ongkir" value="0" class="format-ribuan" oninput="calculateGrandTotal()" /></x-form.group>
                <x-form.group label="Biaya Layanan (Rp)" dalam><x-form.input type="text" inputmode="numeric" name="biaya_layanan" id="biaya_layanan" value="0" class="format-ribuan" oninput="calculateGrandTotal()" /></x-form.group>
                <x-form.group label="Voucher / Diskon Platform (Rp)" dalam><x-form.input type="text" inputmode="numeric" name="voucher" id="voucher" value="0" class="format-ribuan" oninput="calculateGrandTotal()" /></x-form.group>

                <x-form.group label="Opsi Pajak" dalam>
                    <div class="bg-white border border-slate-200 rounded-md p-3 flex items-center justify-between  h-[46px]">
                        <span class="text-xs font-medium text-slate-500 uppercase">PPN 11%</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="tax_toggle" name="include_pajak" class="sr-only peer" onchange="calculateGrandTotal()">
                            <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-indigo-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                </x-form.group>

                <x-form.group label="Metode Bayar" dalam>
                    <x-form.select name="tipe_pembelian" id="tipe_pembelian" onchange="toggleTempo()" required>
                        <option value="Cash">Lunas (dibayar pemilik)</option>
                        <option value="Tempo">Tempo (hutang ke pemasok)</option>
                    </x-form.select>
                </x-form.group>

                <div id="tempo_group" class="hidden">
                    <x-form.group label="Durasi Tempo (Hari)" dalam>
                        <input type="number" name="tempo_hari" id="tempo_hari" min="1" max="365" value="30" oninput="calculateDueDate(); tandaiPilihanTempo()"
                            class="w-full bg-rose-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-rose-500/30 shadow-inner" placeholder="30">
                        {{-- Pilihan cepat termin pembayaran; angka bebas tetap bisa diketik --}}
                        <div class="flex flex-wrap gap-1.5 mt-2" id="pilihan-tempo">
                            @foreach([7, 14, 30, 45, 60] as $h)
                                <button type="button" data-hari="{{ $h }}" onclick="setTempo({{ $h }})" title="Jatuh tempo {{ $h }} hari setelah tanggal transaksi" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-[10px] font-medium uppercase tracking-widest hover:bg-indigo-50 hover:text-indigo-600">{{ $h }} hari</button>
                            @endforeach
                        </div>
                        <p class="text-[11px] font-medium text-slate-400 mt-1.5 ml-1 hidden" id="usulan-tempo"></p>
                    </x-form.group>
                </div>

                <div id="due_date_group" class="hidden">
                    <x-form.group label="Tgl Jatuh Tempo" dalam>
                        <p class="text-[11px] font-medium text-indigo-500 mt-1.5 ml-1 hidden" id="info-jatuh-tempo"></p>
                        <input type="date" name="jatuh_tempo_tgl" id="jatuh_tempo_tgl" readonly
                            class="w-full bg-slate-100 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-500 shadow-inner cursor-not-allowed">
                    </x-form.group>
                </div>
            </div>
        </x-card>

        <x-table>
            <thead>
                <tr>
                    <th class="px-6 py-6 text-left">Nama Bahan</th>
                    <th class="px-3 py-6 text-center w-28">Qty (SJ)</th>
                    <th class="px-4 py-6 text-left w-44">Harga Satuan (Rp)</th>
                    <th class="px-4 py-6 text-left w-44">Diskon &amp; Potongan (Rp)</th>
                    <th class="px-6 py-6 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody id="table-body" class="divide-y divide-slate-50">
                <tr>
                    <td colspan="5" class="py-20 text-center opacity-30">
                        <i class="fas fa-inbox text-5xl mb-4 block"></i>
                        <span class="text-xs font-medium uppercase tracking-widest">Pilih Nomor Surat Jalan Dahulu</span>
                    </td>
                </tr>
            </tbody>
        </x-table>
        <input type="hidden" name="grand_total" id="input_grand_total">

        {{-- RINGKASAN FAKTUR - kartu putih senada modal & kartu lain (sebelumnya baris gelap yang tulisannya tidak terbaca) --}}
        <x-card class="mt-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div class="space-y-2.5 text-sm">
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3">Rincian Perhitungan</p>
                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Subtotal item (setelah diskon &amp; potongan)</span><span class="font-medium text-slate-700" id="rincian-subtotal">Rp 0</span></div>
                    <div class="flex justify-between"><span class="text-slate-500 font-medium">PPN 11%</span><span class="font-medium text-slate-700" id="rincian-ppn">Rp 0</span></div>
                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Ongkir</span><span class="font-medium text-slate-700" id="rincian-ongkir">Rp 0</span></div>
                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Biaya layanan</span><span class="font-medium text-slate-700" id="rincian-layanan">Rp 0</span></div>
                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Voucher / diskon platform</span><span class="font-medium text-rose-500" id="rincian-voucher">- Rp 0</span></div>
                </div>
                <div class="rounded-2xl bg-indigo-50 border border-indigo-100 px-8 py-6 text-right">
                    <p class="text-[10px] font-medium text-indigo-600 uppercase tracking-widest">Net Total Faktur</p>
                    <p class="text-4xl font-medium text-indigo-600 mt-1" id="total-footer">Rp 0</p>
                    <p class="text-[11px] font-medium text-indigo-300 mt-1">Jumlah yang harus dibayar ke supplier</p>
                </div>
            </div>
        </x-card>

        <div class="mt-10 flex justify-end">
            <x-button type="submit" size="default" class="!px-12 !py-5">
                Proses & Simpan Faktur
            </x-button>
        </div>
    </form>
</div>

{{-- MODAL PILIH SURAT JALAN (satu-satunya - sebelumnya ada 2 dgn id sama) --}}
<x-modal id="sj-picker" title="Pilih Surat Jalan" maxWidth="4xl">
    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-widest -mt-4 mb-6">Hanya menampilkan kedatangan yang sudah berstatus 'Selesai'</p>

    <div class="overflow-y-auto max-h-[400px] pr-2">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[10px] font-medium text-slate-500 uppercase tracking-widest border-b border-slate-100">
                    <th class="pb-4">No. Surat Jalan</th>
                    <th class="pb-4">Pemasok</th>
                    <th class="pb-4 text-center">Tgl Datang</th>
                    <th class="pb-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($kedatangans as $sj)
                <tr class="hover:bg-slate-50/80 transition-all group">
                    <td class="py-4 font-medium text-slate-700 uppercase text-sm">{{ $sj->no_surat_jalan }}</td>
                    <td class="py-4 font-medium text-indigo-600 text-[11px] uppercase">{{ $sj->supplier->nama_supplier ?? 'UMUM' }}</td>
                    <td class="py-4 text-center text-slate-500 text-xs">{{ \Carbon\Carbon::parse($sj->tgl_datang)->format('d/m/Y') }}</td>
                    <td class="py-4 text-right">
                        <x-button type="button" size="sm" onclick="selectSJ('{{ $sj->id_kedatangan }}', '{{ $sj->no_surat_jalan }}')">
                            Pilih
                        </x-button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <x-empty-state title="Belum ada Surat Jalan yang tersedia" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-modal>

@endsection

@push('scripts')
<script>
    function selectSJ(id, noSj) {
        document.getElementById('id_kedatangan').value = id;
        document.getElementById('display_no_sj').value = noSj;
        loadKedatanganDetail(id);
        closeModal('sj-picker');
    }

    async function loadKedatanganDetail(id) {
        const tbody = document.getElementById('table-body');
        tbody.innerHTML = `<tr><td colspan="5" class="py-20 text-center"><i class="fas fa-circle-notch fa-spin text-slate-500 text-2xl"></i></td></tr>`;

        try {
            const response = await fetch(`{{ url('/pembelian-bahan/kedatangan-detail') }}?id_kedatangan=${id}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();

            document.getElementById('display_supplier').value = data.supplier || 'UMUM';
            // Usulan termin dari faktur tempo terakhir supplier ini.
            window.tempoUsulan = data.tempo_terakhir || 30;
            const usulan = document.getElementById('usulan-tempo');
            if (data.tempo_terakhir) {
                usulan.textContent = `Termin terakhir dengan supplier ini: ${data.tempo_terakhir} hari`;
                usulan.classList.remove('hidden');
                if (document.getElementById('tipe_pembelian').value === 'Tempo') setTempo(data.tempo_terakhir);
            } else { usulan.classList.add('hidden'); }
            const boxOnline = document.getElementById('info-online-faktur');
            if (data.online) {
                boxOnline.innerHTML = `<i class="fas fa-shopping-bag mr-2"></i>Pembelian online: <b>${escHtml(data.online.platform || '-')}</b>${data.online.toko ? ' &middot; toko ' + escHtml(data.online.toko) : ''}${data.online.no_pesanan ? ' &middot; pesanan ' + escHtml(data.online.no_pesanan) : ''}${data.online.resi ? ' &middot; resi ' + escHtml(data.online.resi) : ''}`;
                boxOnline.classList.remove('hidden');
            } else { boxOnline.classList.add('hidden'); }

            if (data.detail && data.detail.length > 0) {
                tbody.innerHTML = '';
                data.detail.forEach(item => {
                    tbody.innerHTML += `
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-6">
                            <p class="font-medium text-slate-700 text-xs uppercase">${escHtml(item.nama_bahan)}</p>
                            <p class="text-[10px] text-slate-400 font-medium tracking-widest uppercase">${escHtml(item.kode_bahan)}</p>
                            <input type="hidden" name="id_bahan[]" value="${Number(item.id_bahan)}">
                        </td>
                        <td class="px-4 py-6 text-center">
                            <input type="number" name="qty[]" step="0.01" value="${item.qty}" oninput="calculateGrandTotal()" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500  w-24 text-center">
                            <p class="text-[10px] font-medium text-slate-400 mt-1.5 uppercase tracking-widest">${escHtml(item.satuan || 'Pcs')}</p>
                        </td>
                        <td class="px-4 py-6">
                            <input type="text" inputmode="numeric" name="harga[]" value="" placeholder="0" oninput="calculateGrandTotal()" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500  format-ribuan">
                        </td>
                        <td class="px-4 py-6 space-y-2">
                            <div>
                                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1 ml-1">Diskon</label>
                                <input type="text" inputmode="numeric" name="diskon[]" placeholder="0" value="" oninput="calculateGrandTotal()" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500  format-ribuan">
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1 ml-1">Potongan</label>
                                <input type="text" inputmode="numeric" name="potongan[]" placeholder="0" value="" oninput="calculateGrandTotal()" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500  format-ribuan">
                            </div>
                        </td>
                        <td class="px-6 py-6 text-right">
                            <p class="subtotal-text text-base font-medium text-slate-700">Rp 0</p>
                            <input type="hidden" name="subtotal[]" class="subtotal-input">
                        </td>
                    </tr>`;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-slate-400">Tidak ada item di surat jalan ini.</td></tr>`;
            }

            calculateGrandTotal();

        } catch (error) {
            console.error("Error fetching detail:", error);
            Swal.fire('Gagal memuat', 'Rincian surat jalan tidak bisa dimuat. Periksa jaringan lalu coba lagi.', 'error');
        }
    }

    function toggleTempo() {
        const tipe = document.getElementById('tipe_pembelian').value;
        const tempoGroup = document.getElementById('tempo_group');
        const dueGroup = document.getElementById('due_date_group');

        if (tipe === 'Tempo') {
            tempoGroup.classList.remove('hidden');
            dueGroup.classList.remove('hidden');
            // Jangan pernah tanpa nilai: default usulan (30 hari, atau termin terakhir supplier ini).
            const t = document.getElementById('tempo_hari');
            if (!t.value || parseInt(t.value) < 1) t.value = window.tempoUsulan || 30;
            calculateDueDate();
            tandaiPilihanTempo();
        } else {
            tempoGroup.classList.add('hidden');
            dueGroup.classList.add('hidden');
            document.getElementById('tempo_hari').value = '';
            document.getElementById('jatuh_tempo_tgl').value = '';
        }
    }

    function setTempo(hari) {
        document.getElementById('tempo_hari').value = hari;
        calculateDueDate();
        tandaiPilihanTempo();
    }

    function tandaiPilihanTempo() {
        const v = parseInt(document.getElementById('tempo_hari').value) || 0;
        document.querySelectorAll('#pilihan-tempo button').forEach(b => {
            const aktif = parseInt(b.dataset.hari) === v;
            b.classList.toggle('bg-indigo-600', aktif); b.classList.toggle('text-white', aktif);
            b.classList.toggle('bg-slate-100', !aktif); b.classList.toggle('text-slate-500', !aktif);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const t = document.querySelector('input[name="tgl_transaksi"]');
        if (t) t.addEventListener('change', calculateDueDate);
    });

    function calculateDueDate() {
        const tglTrans = document.querySelector('input[name="tgl_transaksi"]').value;
        const hari = document.getElementById('tempo_hari').value;

        const info = document.getElementById('info-jatuh-tempo');
        if (tglTrans && hari) {
            let date = new Date(tglTrans);
            date.setDate(date.getDate() + parseInt(hari));
            document.getElementById('jatuh_tempo_tgl').value = date.toISOString().split('T')[0];
            const teks = date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
            info.textContent = `Jatuh tempo ${teks} (${parseInt(hari)} hari setelah tanggal transaksi)`;
            info.classList.remove('hidden');
        } else {
            info.classList.add('hidden');
        }
    }

    function calculateGrandTotal() {
        const rp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
        let total = 0;
        document.querySelectorAll('#table-body tr').forEach(row => {
            const qtyInput = row.querySelector('[name="qty[]"]');
            if (!qtyInput) return;

            const qty = parseFloat(qtyInput.value) || 0;
            const harga = cleanRupiah(row.querySelector('[name="harga[]"]').value);
            const disc = cleanRupiah(row.querySelector('[name="diskon[]"]').value);
            const pot = cleanRupiah(row.querySelector('[name="potongan[]"]').value);

            const sub = (qty * harga) - disc - pot;
            row.querySelector('.subtotal-text').innerText = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(sub);
            row.querySelector('.subtotal-input').value = sub;
            total += sub;
        });

        const subtotalItem = total;
        const isTaxed = document.getElementById('tax_toggle').checked;
        const ppn = isTaxed ? subtotalItem * 0.11 : 0;
        // Biaya belanja online: ongkir + biaya layanan - voucher (sama dengan hitungan server).
        const ongkir = cleanRupiah(document.getElementById('ongkir').value);
        const layanan = cleanRupiah(document.getElementById('biaya_layanan').value);
        const voucher = cleanRupiah(document.getElementById('voucher').value);
        total = Math.max(0, subtotalItem + ppn + ongkir + layanan - voucher);

        document.getElementById('rincian-subtotal').innerText = rp(subtotalItem);
        document.getElementById('rincian-ppn').innerText = rp(ppn);
        document.getElementById('rincian-ongkir').innerText = rp(ongkir);
        document.getElementById('rincian-layanan').innerText = rp(layanan);
        document.getElementById('rincian-voucher').innerText = '- ' + rp(voucher);

        const formatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(total);
        document.getElementById('total-header').innerText = formatted;
        document.getElementById('total-footer').innerText = formatted;
        document.getElementById('input_grand_total').value = total;
    }
</script>
@endpush
