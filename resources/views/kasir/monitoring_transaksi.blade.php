@extends('layouts.app')

@section('content')
<div class="pwa-ops-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Monitoring Kasir" subtitle="Daftar order yang transaksinya sudah dibuka kasir. Order yang masih menunggu ada di Antrean Baru.">
        <x-slot:actions>
            <x-button type="button" onclick="bukaScanBarcode()" variant="secondary">
                <i class="fas fa-barcode"></i>
                Scan Barcode
            </x-button>
            <x-button href="{{ url('/kasir/waiting-list') }}" class="relative">
                <i class="fas fa-inbox"></i>
                Antrean Baru
                @if($waitingCount > 0)
                    <span class="absolute -top-2 -right-2 bg-rose-600 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-medium border-2 border-white">{{ $waitingCount }}</span>
                @endif
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Notifikasi order baru. Yang naik adalah jumlah ANTREAN, bukan isi
         tabel ini: order baru hanya masuk ke Monitoring setelah kasir menekan
         "Terima Transaksi". Karena itu tombolnya membuka Antrean Baru, bukan
         reload halaman ini (reload tidak akan memunculkan apa-apa). --}}
    <div id="new-data-alert" class="hidden fixed top-24 left-1/2 -translate-x-1/2 z-[100]">
        <button onclick="window.location.href='{{ url('/kasir/waiting-list') }}'" class="bg-indigo-600 text-white px-6 py-3 rounded-full shadow-2xl font-medium text-sm flex items-center gap-3 border-4 border-white">
            <i class="fas fa-bell"></i> Order baru masuk antrean - klik untuk buka antrean
        </button>
    </div>

    {{-- LOCK KASIR - $kasAktif dibagikan global lewat AppServiceProvider.
         Peringatan yang sama juga ada di Antrean Baru & Form Pembayaran
         (di sanalah aksinya benar-benar dikunci) - di sini murni supaya
         siapa pun yang membuka Monitoring langsung tahu kondisinya tanpa
         perlu masuk ke halaman lain dulu. --}}
    @if(!$kasAktif)
    <div class="mb-6 flex flex-wrap items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4">
        <i class="fas fa-triangle-exclamation text-amber-500 text-lg shrink-0"></i>
        <div class="flex-1 min-w-[180px]">
            <p class="text-sm font-medium text-amber-800">Kas kasir belum dibuka</p>
            <p class="text-xs text-amber-600 mt-0.5">Pencatatan pembayaran baru terkunci sampai saldo kas hari ini dibuka.</p>
        </div>
        <x-button href="{{ route('kasir.kelola') }}" class="shrink-0 w-full sm:w-auto">
            <i class="fas fa-wallet"></i> Buka Kas Sekarang
        </x-button>
    </div>
    @endif

    {{-- FILTER + Filter tetap GET status_filtered:1; panel collapsible
         lewat <details> bawaan browser (pola sama dengan SPK/Produksi).
         Bila ada filter GET aktif, panel dibuka langsung dari PHP. --}}
    <x-card class="mb-6">
        <form method="GET" action="{{ route('kasir.index') }}" class="flex flex-wrap items-end gap-4 pwa-ops-filter-form">
            {{-- Penanda "form filter ini memang disubmit" - membedakan nav
                 pertama ke halaman ini (tanpa query string sama sekali,
                 default tampilkan yang belum lunas saja) dari submit
                 eksplisit dengan Status Transaksi dikosongkan/"-- Semua
                 Status --" (yang berarti benar-benar tampilkan semua,
                 termasuk Lunas). Lihat KasirController::index(). --}}
            <input type="hidden" name="status_filtered" value="1">

            <details class="pwa-ops-filter {{ collect(['tgl_mulai', 'tgl_selesai', 'id_konsumen', 'nomor_nota', 'status_bayar'])->some(fn ($k) => request()->filled($k)) ? 'pwa-ops-filter--active' : '' }}" aria-controls="pwa-ops-filter-fields" @if(collect(['tgl_mulai', 'tgl_selesai', 'id_konsumen', 'nomor_nota', 'status_bayar'])->some(fn ($k) => request()->filled($k))) open @endif>
                <summary class="pwa-ops-filter-toggle">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
                    <span>Filter</span>
                </summary>

                <div class="pwa-ops-filter-fields" id="pwa-ops-filter-fields">
            <div class="w-full md:w-40">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Order Dari</label>
                <x-form.input type="date" name="tgl_mulai" value="{{ request('tgl_mulai') }}" />
            </div>

            <div class="w-full md:w-40">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Order Sampai</label>
                <x-form.input type="date" name="tgl_selesai" value="{{ request('tgl_selesai') }}" />
            </div>

            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Konsumen</label>
                <select name="id_konsumen" class="select2-filter w-full">
                    <option value="">-- Semua Konsumen --</option>
                    @foreach($konsumenList as $k)
                        <option value="{{ $k->id_konsumen }}" {{ (string) request('id_konsumen') === (string) $k->id_konsumen ? 'selected' : '' }}>{{ $k->nama_konsumen }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">No. Nota</label>
                <x-form.input type="text" name="nomor_nota" value="{{ request('nomor_nota') }}" placeholder="INV-..." />
            </div>

            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Transaksi</label>
                <x-form.select name="status_bayar">
                    <option value="">-- Semua Status --</option>
                    <option value="Belum Lunas" {{ request('status_bayar') === 'Belum Lunas' ? 'selected' : '' }}>Belum Lunas (Belum Bayar + DP)</option>
                    <option value="Belum Bayar" {{ request('status_bayar') === 'Belum Bayar' ? 'selected' : '' }}>Belum Bayar</option>
                    <option value="DP" {{ request('status_bayar') === 'DP' ? 'selected' : '' }}>DP / Panjar</option>
                    <option value="Lunas" {{ request('status_bayar') === 'Lunas' ? 'selected' : '' }}>Lunas</option>
                </x-form.select>
            </div>

                </div>

                {{-- Aksi submit & reset mematuhi panel filter, seperti
                     pola SPK/Produksi (di desktop, details ber-modesakan
                     display:contents <-> tampilan tidak berubah). --}}
                <div class="pwa-ops-filter-actions">
                    <x-button type="submit">
                        <i class="fas fa-filter"></i>
                        Filter
                    </x-button>

                    @if(request()->anyFilled(['tgl_mulai', 'tgl_selesai', 'id_konsumen', 'nomor_nota', 'status_bayar']))
                        <a href="{{ route('kasir.index') }}" class="h-[42px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                            <i class="fas fa-undo-alt text-sm"></i>
                        </a>
                    @endif
                </div>
            </details>
        </form>
    </x-card>

    <x-table wrapperClass="pwa-ops-shell overflow-hidden" class="pwa-ops-table pwa-ops-table--cashier">
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-12">#</th>
                <th class="px-4 py-5 text-left">Waktu</th>
                <th class="px-4 py-5 text-center">Tipe</th>
                <th class="px-4 py-5 text-left">No. Nota</th>
                <th class="px-4 py-5 text-left">Konsumen</th>
                <th class="px-6 py-5 text-right">Total</th>
                <th class="px-4 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($transaksi as $i => $t)
            <tr class="pwa-ops-row group hover:bg-slate-50/50 transition-all">
                <td class="pwa-ops-cell pwa-ops-sequence px-4 py-5 text-center text-[11px] text-slate-400" data-label="Urutan">{{ $transaksi->firstItem() + $i }}</td>
                <td class="pwa-ops-cell pwa-ops-time px-4 py-5" data-label="Waktu">
                    <span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($t->tgl_order)->translatedFormat('d M Y') }}</span>
                    <div class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($t->tgl_order)->format('H:i') }}</div>
                </td>
                <td class="pwa-ops-cell pwa-ops-type px-4 py-5 text-center" data-label="Tipe">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-medium {{ $t->tipe_order === 'PO' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-slate-50 text-slate-500 border border-slate-100' }}">
                        {{ $t->tipe_order }}
                    </span>
                </td>
                <td class="pwa-ops-cell pwa-ops-note px-4 py-5" data-label="Nomor Nota">
                    <span class="text-[13px] font-medium text-indigo-600">#{{ $t->nomor_nota }}</span>
                    <div class="text-[10px] text-slate-400">Kasir: {{ $t->username }}</div>
                </td>
                <td class="pwa-ops-cell pwa-ops-customer px-4 py-5" data-label="Konsumen">
                    <span class="text-[13px] font-medium text-slate-700">{{ $t->nama_konsumen }}</span>
                </td>
                <td class="pwa-ops-cell pwa-ops-total px-6 py-5 text-right" data-label="Total Pembayaran">
                    <span class="text-[13px] font-medium text-slate-800">Rp {{ number_format($t->total_bayar, 0, ',', '.') }}</span>
                    {{-- Rincian "sudah terbayar berapa" untuk status DP - data
                         ini sebelumnya SUDAH diambil lewat subquery total_masuk
                         (lihat KasirController::index()) tapi tidak pernah
                         ditampilkan di sini, jadi begitu status berubah dari DP
                         ke Lunas, jejak cicilannya seolah "menghilang" karena
                         cuma bisa dilihat lewat modal Riwayat Proses terpisah.

                         Syaratnya memakai display_status_pembayaran, yaitu status
                         TURUNAN yang sama dipakai badge di sebelah kanan,
                         bukan lagi kolom transaksi.status_pembayaran. Kolom itu
                         salinan denormalisasi yang bisa tertinggal dari
                         pembayaran_riwayat. Kalau keduanya berbeda, badge di
                         sebelah kanan memakai status turunan sementara rincian
                         ini memakai kolom salinan, sehingga kasir bisa melihat
                         badge DP tanpa melihat nominal yang sudah dibayar. --}}
                    @if($t->display_status_pembayaran === 'DP')
                        <div class="text-[10px] text-emerald-500 font-medium mt-0.5">Terbayar Rp {{ number_format($t->total_masuk ?? 0, 0, ',', '.') }}</div>
                        <div class="text-[10px] text-amber-500 font-medium">Sisa Rp {{ number_format(max(0, $t->total_bayar - ($t->total_masuk ?? 0)), 0, ',', '.') }}</div>
                    @endif
                </td>
                <td class="pwa-ops-cell pwa-ops-status px-4 py-5 text-center" data-label="Status">
                    <x-badge :status="$t->display_status_pembayaran" />
                    {{---status_order hanya bisa 'Diterima Kasir' di halaman ini
                         (KasirController::index() sudah menyaringnya). Kedua
                         cabang di bawah dipertahankan sebagai jaring pengaman
                         kalau syarat itu suatu saat dilonggarkan lagi. --}}
                    @if(($t->status_order ?? null) === 'Menunggu Kasir')
                        <div class="mt-1"><span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-100">Menunggu Kasir</span></div>
                    @elseif(($t->status_order ?? null) === 'Diterima Kasir')
                        <div class="mt-1"><span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Sudah Diterima</span></div>
                    @endif
                </td>
                <td class="pwa-ops-cell pwa-ops-actions px-8 py-5 text-center" data-label="Aksi">
                    <div class="flex items-center justify-center gap-2">
                        @if(($t->status_order ?? 'Diterima Kasir') === 'Menunggu Kasir')
                            <form method="POST" action="{{ route('kasir.terimaTransaksi', $t->id_transaksi) }}" class="inline">
                                @csrf
                                <input type="hidden" name="submit_id" value="terima-mon-{{ $t->id_transaksi }}-{{ $i }}">
                                <x-button type="submit" size="icon" variant="ghost" class="border border-indigo-100 hover:text-indigo-600" title="Terima Transaksi">
                                    <i class="fas fa-check-circle text-xs"></i>
                                </x-button>
                            </form>
                        @else
                            <x-button href="{{ url('/kasir/bayar-form/'.$t->id_transaksi) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-emerald-600" title="Input Pembayaran">
                                <i class="fas fa-money-bill-wave text-xs"></i>
                            </x-button>
                        @endif

                        {{-- Format kertas (thermal/A4/continuous) sekarang GLOBAL dari
                             Pengaturan Aplikasi - dulu dropdown 2 pilihan di sini,
                             kasir tidak perlu pilih format tiap kali cetak lagi. --}}
                        <x-button href="{{ url('/kasir/print-opsi/'.$t->id_transaksi) }}" target="_blank" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Cetak Nota">
                            <i class="fas fa-print text-xs"></i>
                        </x-button>

                        <x-button type="button" size="icon" variant="ghost" onclick="showHistory('{{ $t->id_transaksi }}')" class="border border-slate-100 hover:text-slate-800" title="Riwayat Proses">
                            <i class="fas fa-clock-rotate-left text-xs"></i>
                        </x-button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <x-empty-state title="Tidak ada transaksi" description="Order yang transaksinya sudah dibuka kasir akan tampil di sini. Order yang masih menunggu ada di Antrean Baru." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$transaksi" />

</div>

{{-- MODAL SCAN BARCODE - barcode di SPK/nota (lihat spk/print.blade.php)
     berisi "TRX"+id_transaksi. Dua cara pindai: (1) scanner USB/Bluetooth
     genggam yang berperilaku seperti keyboard - cukup fokus di kolom
     manual lalu scan, otomatis "mengetik" kode + Enter; (2) kamera HP/
     laptop lewat library html5-qrcode (CDN, dimuat sekali saat modal
     pertama dibuka, bukan di setiap halaman). Keduanya berujung ke fungsi
     yang sama (prosesKodeBarcode) yang mengarahkan ke halaman Bayar
     transaksi terkait. --}}
<x-modal id="scan-barcode" title="Scan Barcode SPK / Nota" maxWidth="md">
    <div class="space-y-5">
        <div>
            <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Scan pakai alat scanner</label>
            <input type="text" id="input-scan-manual" placeholder="Arahkan scanner ke sini lalu tembak..." autocomplete="off"
                class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-center tracking-widest focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            <p class="text-[11px] text-slate-400 mt-1.5 ml-2">Kolom ini otomatis aktif - tinggal scan, tidak perlu klik dulu.</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex-1 h-px bg-slate-100"></div>
            <span class="text-[10px] font-medium text-slate-500 uppercase">Atau</span>
            <div class="flex-1 h-px bg-slate-100"></div>
        </div>

        <div>
            <x-button type="button" id="btn-buka-kamera-scan" onclick="mulaiScanKamera()" variant="secondary" class="w-full">
                <i class="fas fa-camera"></i> Scan Pakai Kamera HP/Laptop
            </x-button>
            <div id="reader-barcode" class="hidden mt-4 rounded-xl overflow-hidden"></div>
            <p id="status-kamera-scan" class="text-[11px] text-slate-400 mt-2 text-center"></p>
        </div>
    </div>
</x-modal>

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2-filter').select2({ width: '100%', placeholder: '-- Semua Konsumen --', allowClear: true });
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }


    async function showHistory(id) {
        Swal.fire({ title: 'Memuat...', didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch(`{{ url('/kasir/history') }}/${id}`);
            const data = await res.json();

            if (data.status !== 'success') {
                return Swal.fire('Gagal', data.msg || 'Data tidak ditemukan.', 'error');
            }

            const itemRows = data.items.map(it =>
                `<tr>
                    <td class="py-1 pr-2">${escapeHtml(it.nama_file)}</td>
                    <td class="py-1 pr-2 text-center">${it.qty}${it.qty_cetak !== null ? ' / ' + it.qty_cetak : ''}</td>
                    <td class="py-1 text-right font-medium">${it.status_spk}</td>
                </tr>`
            ).join('');

            const bayarRows = data.pembayaran.length
                ? data.pembayaran.map(p =>
                    `<tr>
                        <td class="py-1 pr-2">${new Date(p.tgl_bayar).toLocaleString('id-ID')}</td>
                        <td class="py-1 pr-2">${p.metode}</td>
                        <td class="py-1 text-right font-medium">Rp ${new Intl.NumberFormat('id-ID').format(p.nominal)}</td>
                    </tr>`
                  ).join('')
                : '<tr><td colspan="3" class="py-2 text-slate-400 italic">Belum ada pembayaran tercatat.</td></tr>';

            Swal.fire({
                title: 'Nota ' + data.nomor_nota,
                width: 480,
                html: `
                    <div class="text-left text-xs">
                        <p class="text-slate-500 mb-3">Order: ${new Date(data.tgl_order).toLocaleString('id-ID')} &middot; Status: <b>${data.status_pembayaran}</b></p>
                        <p class="font-medium uppercase text-[10px] text-slate-400 mb-1">Item &amp; Status SPK (qty order / qty cetak)</p>
                        <table class="w-full mb-4">${itemRows}</table>
                        <p class="font-medium uppercase text-[10px] text-slate-400 mb-1">Riwayat Pembayaran</p>
                        <table class="w-full">${bayarRows}</table>
                    </div>
                `,
                icon: undefined,
                customClass: {}
            });
        } catch (e) {
            Swal.fire('Gagal memuat', 'Riwayat pembayaran tidak bisa dimuat. Coba lagi.', 'error');
        }
    }

    // Polling jumlah antrean baru (endpoint /kasir/check-pembayaran menghitung
    // nota status_order='Menunggu Kasir', sama persis dengan lencana "Antrean
    // Baru"). Yang dipantau adalah ANTREAN, bukan tabel ini - makanya banner
    // mengarah ke Antrean Baru.
    let initialWaitingCount = {{ $waitingCount ?? 0 }};

    function checkNewTransaction() {
        fetch("{{ url('/kasir/check-pembayaran') }}")
            .then(res => res.json())
            .then(data => {
                if (data.total > initialWaitingCount) {
                    document.getElementById('new-data-alert').classList.remove('hidden');
                }
            });
    }
    setInterval(checkNewTransaction, 10000);

    // SCAN BARCODE (lihat catatan di atas modal "scan-barcode") - barcode
    // berisi "TRX"+id_transaksi, kode lain (mis. barcode produk pihak
    // ketiga yang kebetulan ikut terpindai) ditolak dengan pesan jelas
    // alih-alih diam-diam redirect ke URL yang salah.
    function bukaScanBarcode() {
        openModal('scan-barcode');
        document.getElementById('input-scan-manual').value = '';
        setTimeout(() => document.getElementById('input-scan-manual').focus(), 150);
    }

    document.getElementById('input-scan-manual').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        prosesKodeBarcode(this.value);
    });

    function prosesKodeBarcode(kode) {
        // Kode bisa "TRX123" (barcode lama / ketik manual) atau ALAMAT pembayaran dari QR (.../kasir/bayar-form/123).
        const teks = (kode || '').trim();
        const cocok = /^TRX(\d+)$/i.exec(teks) || /\/kasir\/bayar-form\/(\d+)\/?(?:[?#].*)?$/i.exec(teks);
        if (!cocok) {
            Swal.fire('Kode Tidak Dikenali', 'Barcode ini bukan barcode SPK/Nota yang valid dari aplikasi.', 'error');
            document.getElementById('input-scan-manual').value = '';
            document.getElementById('input-scan-manual').focus();
            return;
        }
        window.location.href = `{{ url('/kasir/bayar-form') }}/${cocok[1]}`;
    }

    // Kamera dimuat MALAS (lazy) - library html5-qrcode (CDN) baru
    // diambil begitu tombol "Scan Pakai Kamera" pertama kali ditekan,
    // bukan di setiap kunjungan halaman Monitoring Kasir yang notabene
    // dibuka berkali-kali sehari.
    let html5QrcodeScannerAktif = null;
    let libHtml5QrcodeSudahDimuat = false;

    function muatLibHtml5Qrcode() {
        return new Promise((resolve, reject) => {
            if (libHtml5QrcodeSudahDimuat) return resolve();
            const script = document.createElement('script');
            script.src = '{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}';
            script.onload = () => { libHtml5QrcodeSudahDimuat = true; resolve(); };
            script.onerror = () => reject(new Error('Gagal memuat library kamera.'));
            document.head.appendChild(script);
        });
    }

    /* Peta error kamera ke bahasa yang bisa ditindaklanjuti.

       Sebelumnya SEMUA kegagalan jatuh ke satu kalimat: "pastikan izin
       kamera diberikan". Itu yang membuat bug ini sulit dilaporkan -
       penyebab sebenarnya (kamera dipakai aplikasi lain, tidak ada
       kamera belakang, halaman dibuka lewat HTTP, kamera tidak
       didukung) semuanya terdengar seperti masalah izin yang sama,
       padahal cara mengatasinya berbeda. Jadi setiap kemungkinan
       ditulis terpisah, dengan langkah yang benar-benar berguna.
    */
    function jelaskanErrorKamera(e) {
        const nama = e && e.name ? e.name : '';
        const pesanAsli = e && e.message ? e.message : String(e || '');

        if (nama === 'NotAllowedError' || /permission|denied/i.test(pesanAsli)) {
            return {
                judul: 'Izin kamera belum diberikan',
                arah: 'Izin ini sengaja tidak diminta otomatis supaya tidak muncul mendadak. ' +
                      'Tekan ikon gembok di bilah alamat, pilih "Izin situs" -> Kamera -> "Izinkan", ' +
                      'lalu muat ulang halaman ini. Kalau masih ditolak, cek pengaturan situs di browser HP.',
            };
        }
        if (nama === 'NotFoundError' || /Requested device not found/i.test(pesanAsli)) {
            return {
                judul: 'Tidak ada kamera yang bisa dipakai',
                arah: 'Browser tidak menemukan kamera belakang. Tutup dulu aplikasi yang sedang memakai ' +
                      'kamera (kamera, video call, scan QR lain) - beberapa HP mematikan kamera ' +
                      'begitu ada aplikasi lain yang memakainya.',
            };
        }
        if (nama === 'NotReadableError' || /Could not start|being used|track started/i.test(pesanAsli)) {
            return {
                judul: 'Kamera sedang dipakai aplikasi lain',
                arah: 'Tutup aplikasi kamera, WhatsApp video, atau scan QR lain yang sedang berjalan, ' +
                      'lalu coba lagi.',
            };
        }
        if (nama === 'OverconstrainedError' || nama === 'ConstraintNotSatisfiedError') {
            return {
                judul: 'Kamera tidak mendukung mode yang diminta',
                arah: 'Halaman ini meminta kamera belakang dan tidak ada yang cocok. Kamera depan masih ' +
                      'bisa dipakai, atau langsung pakai kolom kode manual di bawah.',
            };
        }
        if (nama === 'SecurityError' || !window.isSecureContext) {
            return {
                judul: 'Kamera butuh koneksi HTTPS',
                arah: 'Browser hanya mengizinkan akses kamera di alamat HTTPS. Kalau halaman ini dibuka lewat ' +
                      'http:// atau alamat IP lokal, kamera tidak akan pernah bisa jalan walaupun izin sudah ' +
                      'diberikan. Pakai kolom kode manual di bawah, atau minta server dipasang HTTPS.',
            };
        }
        return {
            judul: 'Kamera gagal dibuka',
            arah: 'Browser melaporkan: ' + (pesanAsli || 'tanpa keterangan') + '. ' +
                  'Pakai kolom kode manual di bawah sebagai sementara.',
        };
    }

    async function mulaiScanKamera() {
        const statusEl = document.getElementById('status-kamera-scan');
        const readerEl = document.getElementById('reader-barcode');

        // getUserMedia hanya ada di konteks aman (HTTPS atau localhost).
        // Dicek lebih dulu supaya pengguna diberi tahu penyebab yang
        // sebenarnya, bukan "izin belum diberikan" yang tidak berguna.
        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            const info = jelaskanErrorKamera({ name: 'SecurityError' });
            Swal.fire({
                title: info.judul,
                html: '<div style="text-align:left;font-size:13px;line-height:1.6">' + info.arah + '</div>',
                icon: 'warning',
                confirmButtonText: 'Mengerti',
            });
            return;
        }

        statusEl.textContent = 'Memuat kamera...';
        try {
            await muatLibHtml5Qrcode();
        } catch (e) {
            statusEl.textContent = 'Gagal memuat library kamera - muat ulang halaman.';
            return;
        }

        readerEl.classList.remove('hidden');
        if (html5QrcodeScannerAktif) return;

        html5QrcodeScannerAktif = new Html5Qrcode('reader-barcode', {
            formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128],
        });

        /* facingMode ditulis sebagai { ideal: ... } BUKAN { exact: ... }.

           Ini penyebab kedua bug ini di HP. Dengan exact, browser HARUS
           menemukan kamera yang persis memenuhi permintaan itu; kalau
           tidak ada, ia menolak tanpa mencoba yang lain. Kamera
           belakang sering tidak tersedia persis saat dibutuhkan - sedang
           dipakai aplikasi lain, atau mode kamera aktif berbeda. 'ideal'
           berarti "pilih yang belakang kalau ada, kalau tidak pakai
           yang ada". Untuk scan barcode, kamera depan masih bisa
           dipakai, jadi menolak seluruhnya hanya karena tidak dapat
           yang ideal adalah keputusan yang salah.
        */
        try {
            await html5QrcodeScannerAktif.start(
                { facingMode: { ideal: 'environment' } },
                { fps: 10, qrbox: { width: 260, height: 120 }, aspectRatio: 2.0 },
                (kodeTerbaca) => {
                    statusEl.textContent = 'Terbaca: ' + kodeTerbaca;
                    html5QrcodeScannerAktif.stop().then(() => {
                        html5QrcodeScannerAktif = null;
                        prosesKodeBarcode(kodeTerbaca);
                    });
                },
                () => { /* frame tanpa barcode - abaikan, ini dipanggil terus tiap frame */ }
            );
            statusEl.textContent = 'Arahkan kamera ke barcode...';
        } catch (e) {
            const info = jelaskanErrorKamera(e);
            statusEl.textContent = info.judul;
            Swal.fire({
                title: info.judul,
                html: '<div style="text-align:left;font-size:13px;line-height:1.6">' + info.arah + '</div>',
                icon: 'warning',
                confirmButtonText: 'Mengerti',
            });
            try { await html5QrcodeScannerAktif.stop(); } catch (e2) { /* belum start, tidak apa-apa */ }
            html5QrcodeScannerAktif = null;
            readerEl.classList.add('hidden');
        }
    }
</script>
@endpush
@endsection
