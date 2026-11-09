{{--
    Modal pelunasan hutang supplier (satu faktur, atau semua hutang satu supplier sekaligus).
    Dipakai di Pembelian Bahan & Laporan Hutang. Buka lewat: bukaPelunasan('faktur', idFaktur) / bukaPelunasan('supplier', idSupplier).
    Memakai partials.rupiah-format-script (angkaId, formatUangDariAngka, cleanUangDesimal, format-ribuan-desimal)
    yang dimuat global lewat layouts/app. Nominal ditampilkan & divalidasi EXACT (pecahan dipertahankan):
    grand_total bisa berpecahan (mis. PPN 11%), jadi Math.round/cleanRupiah di sini DILARANG - pernah membuat
    sisa Rp 999,60 tampil "Rp 1.000" lalu nominal 1000 itu ditolak, dan operator tidak bisa melunasi.
    (plRp didefinisikan di blok script bawah memakai angkaId.)
--}}
<x-modal id="pelunasan-hutang" title="Pelunasan Hutang Pemasok" maxWidth="2xl">
    <form method="POST" id="form-pelunasan" action="#">
        @csrf
        <input type="hidden" name="_method" id="pl-method" value="PATCH">

        {{-- Ringkasan --}}
        <div class="rounded-2xl bg-slate-50 p-5 mb-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest" id="pl-label">Faktur</p>
                    <p class="text-base font-medium text-slate-800" id="pl-judul">-</p>
                    <p class="text-xs font-medium text-indigo-500 uppercase" id="pl-supplier">-</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Sisa Hutang</p>
                    <p class="text-2xl font-medium text-rose-500" id="pl-sisa">Rp 0</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-slate-100 text-xs">
                <div><span class="text-slate-400 font-medium">Total tagihan</span><div class="font-medium text-slate-700" id="pl-total">Rp 0</div></div>
                <div class="text-right"><span class="text-slate-400 font-medium">Sudah dibayar</span><div class="font-medium text-emerald-600" id="pl-dibayar">Rp 0</div></div>
            </div>
        </div>

        {{-- Daftar faktur (mode supplier) / jatuh tempo --}}
        <div id="pl-faktur-box" class="mb-5 hidden">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-1" id="pl-faktur-judul">Faktur yang dilunasi (paling lama jatuh tempo lebih dulu)</p>
            <div class="rounded-2xl border border-slate-100 divide-y divide-slate-50 max-h-40 overflow-y-auto" id="pl-faktur-list"></div>
        </div>

        {{-- Isian --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-2">
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Nominal Dibayar (Rp)</label>
                <x-form.input type="text" inputmode="decimal" name="nominal" id="pl-nominal" class="format-ribuan-desimal" autocomplete="off" required />
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="plIsiNominal(1)" title="Isi nominal dengan seluruh sisa hutang" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 text-[10px] font-medium uppercase tracking-widest hover:bg-indigo-100">Lunasi penuh</button>
                    <button type="button" onclick="plIsiNominal(0.5)" title="Isi nominal dengan setengah dari sisa hutang" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-[10px] font-medium uppercase tracking-widest hover:bg-slate-200">Setengah</button>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Tanggal Bayar</label>
                <x-form.input type="date" name="tanggal" id="pl-tanggal" required />
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cara Bayar</label>
                <x-form.select name="metode" id="pl-metode" onchange="plGantiSumber()">
                    <option value="Transfer">Transfer</option>
                    <option value="Tunai">Tunai</option>
                </x-form.select>
                <p class="text-[11px] font-medium mt-2 ml-2 text-slate-400" id="pl-info-sumber"></p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Keterangan / No. Bukti (opsional)</label>
                <x-form.input type="text" name="keterangan" maxlength="255" placeholder="mis. bukti transfer BCA 1234 / cicilan ke-2" />
            </div>
        </div>

        {{-- Riwayat cicilan (mode faktur) --}}
        <div id="pl-riwayat-box" class="mt-5 hidden">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-1">Riwayat Pembayaran</p>
            <div class="rounded-2xl border border-slate-100 divide-y divide-slate-50 max-h-32 overflow-y-auto text-xs" id="pl-riwayat-list"></div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6 pt-5 border-t border-slate-100">
            <button type="button" onclick="closeModal('pelunasan-hutang')" class="px-6 py-3 rounded-xl text-[11px] font-medium uppercase tracking-widest text-slate-400 hover:bg-slate-50">Batal</button>
            <x-button type="submit" id="pl-submit" title="Catat pembayaran hutang ini"><i class="fas fa-check"></i> Catat Pembayaran</x-button>
        </div>
    </form>
</x-modal>

<script>
    // Exact: pecahan dipertahankan ("999,6", bukan "1.000"). Nilai bulat tampil sama seperti sebelumnya.
    const plRp = n => 'Rp ' + angkaId(n, 2);
    const plEsc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let plData = null;

    async function bukaPelunasan(mode, id) {
        try {
            const url = new URL(`{{ route('pembelian.pelunasan-info') }}`, window.location.origin);
            url.searchParams.set(mode, id);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            plData = await res.json();
        } catch (e) {
            Swal.fire('Gagal', 'Data pelunasan tidak dapat dimuat. Coba muat ulang halaman.', 'error');
            return;
        }
        const d = plData, form = document.getElementById('form-pelunasan');
        form.action = d.action;
        document.getElementById('pl-method').value = d.mode === 'faktur' ? 'PATCH' : 'POST';
        document.getElementById('pl-label').textContent = d.mode === 'faktur' ? 'No. Faktur' : 'Pemasok (semua faktur berhutang)';
        document.getElementById('pl-judul').textContent = d.judul;
        document.getElementById('pl-supplier').textContent = d.mode === 'faktur' ? d.supplier : (d.fakturs.length + ' faktur belum lunas');
        document.getElementById('pl-sisa').textContent = plRp(d.sisa);
        document.getElementById('pl-total').textContent = plRp(d.total);
        document.getElementById('pl-dibayar').textContent = plRp(d.dibayar);

        const fBox = document.getElementById('pl-faktur-box');
        document.getElementById('pl-faktur-judul').textContent = d.mode === 'faktur' ? 'Jatuh tempo' : 'Faktur yang dilunasi (paling lama jatuh tempo lebih dulu)';
        document.getElementById('pl-faktur-list').innerHTML = d.fakturs.map(f => `
            <div class="flex items-center justify-between px-4 py-2.5 text-xs">
                <div><span class="font-medium text-slate-700">${plEsc(f.no_faktur)}</span>
                    <span class="text-slate-400 ml-2">jatuh tempo ${plEsc(f.jatuh_tempo)}</span>
                    ${f.telat_hari > 0 ? `<span class="ml-2 text-[10px] font-medium uppercase text-rose-500 bg-rose-50 rounded px-2 py-0.5">telat ${f.telat_hari} hari</span>` : ''}</div>
                <span class="font-medium text-slate-600">${plRp(f.sisa)}</span>
            </div>`).join('');
        fBox.classList.toggle('hidden', d.fakturs.length === 0);

        const rBox = document.getElementById('pl-riwayat-box');
        document.getElementById('pl-riwayat-list').innerHTML = (d.riwayat || []).map(r => `
            <div class="flex items-center justify-between px-4 py-2"><span class="text-slate-500">${plEsc(r.tanggal)} &middot; ${plEsc(r.metode)}${r.keterangan ? ' &middot; ' + plEsc(r.keterangan) : ''}</span><span class="font-medium text-emerald-600">${plRp(r.nominal)}</span></div>`).join('');
        rBox.classList.toggle('hidden', !(d.riwayat && d.riwayat.length));

        document.getElementById('pl-tanggal').value = new Date().toISOString().slice(0, 10);
        document.getElementById('pl-metode').value = 'Transfer';
        plIsiNominal(1);
        plGantiSumber();
        openModal('pelunasan-hutang');
    }

    function plIsiNominal(bagian) {
        // Exact: sisa pecahan diisi apa adanya ("999,6"), bukan Math.round.
        const el = document.getElementById('pl-nominal');
        el.value = formatUangDariAngka(plData.sisa * bagian);
        el.focus();
        el.select();
    }

    // Pembayaran bahan dilakukan pemilik di luar semua kas: dicatat apa adanya tanpa mengurangi kas laci, Kas Kecil, atau bank.
    function plGantiSumber() {
        const info = document.getElementById('pl-info-sumber');
        info.className = 'text-[11px] font-medium mt-2 ml-2 text-slate-400';
        info.textContent = 'Dibayar pemilik di luar kas. Tercatat sebagai dana pemilik, tidak mengurangi kas laci, Kas Kecil, atau bank.';
    }

    document.getElementById('form-pelunasan').addEventListener('submit', function (e) {
        // cleanUangDesimal: "1.000" -> 1000, "999,6" -> 999.6. Validasi lawan sisa EXACT.
        // Nilai input SENGAJA tidak ditulis ulang ke angka polos di sini: pembersih global
        // (bersihkanFormUang) yang mengubah tampilan Indonesia menjadi angka kirim untuk server.
        const nominal = cleanUangDesimal(document.getElementById('pl-nominal').value);
        let pesan = null;
        if (!nominal || nominal <= 0) pesan = 'Nominal harus lebih dari 0.';
        else if (nominal > plData.sisa + 0.01) pesan = 'Nominal melebihi sisa hutang ' + plRp(plData.sisa) + '.';
        if (pesan) { e.preventDefault(); Swal.fire('Periksa lagi', pesan, 'warning'); return; }
        document.getElementById('pl-submit').disabled = true;
    });
</script>
