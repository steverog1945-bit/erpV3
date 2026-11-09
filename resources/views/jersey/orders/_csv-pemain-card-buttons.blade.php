{{-- Tombol Import CSV + Template CSV untuk Daftar Pemain satu kartu barang
     (di-include dari _line-card-fields, tampil di semua kartu). Fungsinya
     didefinisikan di _csv-pemain-tools.blade.php (sekali per halaman). --}}
<button type="button" onclick="csvPemainUnduhTemplate()" class="text-[10px] font-medium text-slate-400 uppercase underline hover:text-indigo-600">Template CSV</button>
<button type="button" onclick="csvPemainMulai(this)" class="btn-import-csv-pemain text-[10px] font-medium text-emerald-600 uppercase underline hover:text-emerald-700">Import CSV</button>
