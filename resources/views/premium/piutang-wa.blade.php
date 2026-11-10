@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
    <x-page-header title="Piutang · Kirim Pengingat WA" subtitle="Satu klik: WhatsApp di perangkat Anda terbuka dengan pesan pengingat yang sudah terisi. Tidak ada data yang dikirim ke server pihak ketiga.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
            <button type="button" id="salinSemua" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-slate-200 bg-white text-[12px] font-semibold text-slate-600 hover:border-slate-300 hover:text-slate-800 transition-colors"><i class="far fa-copy"></i> Salin semua pesan</button>
        </x-slot:actions>
    </x-page-header>

    <div class="rounded-2xl border border-slate-200/70 bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,30,62,.05)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px] min-w-[860px]">
                <thead>
                    <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-[#F7F9FE]">
                        <th class="px-6 py-3">Nota</th>
                        <th class="px-6 py-3">Pelanggan</th>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Total</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($rows as $r)
                        <tr class="hover:bg-[#FAFCFF] transition-colors">
                            <td class="px-6 py-3.5 font-medium text-slate-800">{{ $r->nomor_nota }}</td>
                            <td class="px-6 py-3.5">
                                <p class="font-medium text-slate-700 leading-tight">{{ $r->nama }}</p>
                                <p class="text-[10.5px] text-slate-400 tabular-nums">{{ $r->telepon ?: 'tanpa nomor WA' }}</p>
                            </td>
                            <td class="px-6 py-3.5 text-slate-500">{{ \Carbon\Carbon::parse($r->tgl_order)->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-wide {{ $r->status_pembayaran === 'DP' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-600 border border-rose-200' }}">{{ $r->status_pembayaran }}</span></td>
                            <td class="px-6 py-3.5 text-right font-semibold text-slate-800 tabular-nums">Rp {{ number_format((float) $r->total_bayar, 0, ',', '.') }}</td>
                            <td class="px-6 py-3.5">
                                <div class="flex items-center justify-end gap-2">
                                    @if($r->wa_link)
                                        <a href="{{ $r->wa_link }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-[#25D366] text-[#053b1e] text-[11px] font-bold hover:brightness-95 transition" title="Buka WhatsApp dengan pesan terisi">
                                            <i class="fab fa-whatsapp"></i> WA
                                        </a>
                                    @else
                                        <span class="text-[10px] text-slate-400" title="Nomor WA tidak tersedia / tidak valid">tanpa WA</span>
                                    @endif
                                    <button type="button" class="salin-pesan inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 text-[11px] font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700 transition-colors" data-pesan="{{ $r->pesan }}" title="Salin pesan ke clipboard"><i class="far fa-copy"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400"><i class="fas fa-circle-check text-emerald-400 text-2xl mb-2 block"></i>Tidak ada nota belum lunas 60 hari terakhir. Semua beres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4 text-[11px] text-slate-400">Menampilkan maksimal 40 nota terbaru (60 hari). Pesan dibangun dari data nota — periksa sekali lagi sebelum mengirim. Pengingat otomatis terjadwal menyusul saat gateway resmi diaktifkan.</p>
</div>

<script>
(function () {
    function toast(t) {
        if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', timer: 1800, showConfirmButton: false, icon: 'success', title: t }); }
    }
    async function salin(teks) {
        try { await navigator.clipboard.writeText(teks); toast('Pesan tersalin'); }
        catch (e) {
            var ta = document.createElement('textarea');
            ta.value = teks; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); toast('Pesan tersalin'); } catch (e2) { toast('Gagal menyalin'); }
            document.body.removeChild(ta);
        }
    }
    document.querySelectorAll('.salin-pesan').forEach(function (btn) {
        btn.addEventListener('click', function () { salin(btn.getAttribute('data-pesan') || ''); });
    });
    var semua = document.getElementById('salinSemua');
    if (semua) semua.addEventListener('click', function () {
        var teks = Array.prototype.slice.call(document.querySelectorAll('.salin-pesan'))
            .map(function (b) { return b.getAttribute('data-pesan'); })
            .filter(Boolean)
            .join('\n\n———\n\n');
        salin(teks || 'Tidak ada pesan untuk disalin.');
    });
})();
</script>
@endsection
