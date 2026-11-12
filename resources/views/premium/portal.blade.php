@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
    <x-page-header title="Portal Lacak Pelanggan" subtitle="Tautan unik berumur 7 hari per nota. Kirim tautannya lewat WhatsApp/SMS - pelanggan melihat progres produksi tanpa login.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
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
                        <th class="px-6 py-3">Tautan Lacak (7 hari)</th>
                        <th class="px-6 py-3 text-right">Salin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($notas as $n)
                        <tr class="hover:bg-[#FAFCFF] transition-colors">
                            <td class="px-6 py-3.5 font-semibold text-slate-800">{{ $n->nomor_nota }}</td>
                            <td class="px-6 py-3.5 text-slate-700">{{ $n->nama }}</td>
                            <td class="px-6 py-3.5 text-slate-500">{{ \Carbon\Carbon::parse($n->tgl_order)->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-3.5"><code class="block max-w-[340px] truncate text-[10.5px] text-indigo-700 bg-indigo-50/60 border border-indigo-100 rounded-md px-2 py-1.5">{{ $n->lacak_url }}</code></td>
                            <td class="px-6 py-3.5 text-right"><button type="button" class="salin-lacak inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 text-[11px] font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700 transition-colors" data-url="{{ $n->lacak_url }}"><i class="far fa-copy"></i> Salin</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada nota aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-start gap-4 text-[11px] text-slate-400 leading-relaxed max-w-3xl">
        <p><i class="fas fa-circle-info mr-1.5 text-slate-300"></i><strong>Cara pakai:</strong> klik Salin, tempel ke chat pelanggan. Pelanggan membuka tautan tanpa login — halaman menampilkan progres item (Antre / Diproses / Cetak / Selesai), status pembayaran, dan status pengambilan.</p>
        <p><i class="fas fa-shield-halved mr-1.5 text-slate-300"></i><strong>Privasi:</strong> tautan ditandatangani dengan kunci aplikasi, kedaluwarsa 7 hari, dan tidak memuat HPP, komisi, maupun data pelanggan lain. Tautan yang bocor hanya memperlihatkan progres satu nota.</p>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.salin-lacak').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var url = btn.getAttribute('data-url');
            try { await navigator.clipboard.writeText(url); }
            catch (e) {
                var ta = document.createElement('textarea');
                ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (e2) {}
                document.body.removeChild(ta);
            }
            var label = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-emerald-600"></i> Tersalin';
            setTimeout(function () { btn.innerHTML = label; }, 1600);
        });
    });
})();
</script>
@endsection
