{{-- Modal "Pemasok Baru" - pola sama dengan modal Konsumen Baru di Input Pesanan. Param: $selectId (id <select> supplier). --}}
<x-modal id="supplier-baru" title="Input Pemasok Baru" maxWidth="md">
    <div class="space-y-5">
        <x-form.group label="Nama Perusahaan/Vendor" dalam>
            <x-form.input type="text" id="new_supplier_nama" placeholder="Contoh: PT. Sumber Kertas" />
        </x-form.group>
        <x-form.group label="No. Telepon / Sales" dalam>
            <x-form.input type="text" id="new_supplier_kontak" placeholder="08xx xxxx xxxx" />
        </x-form.group>
        <x-form.group label="Alamat Lengkap" dalam>
            <textarea id="new_supplier_alamat" placeholder="Jl. Raya Percetakan No. 1..."
                      class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"></textarea>
        </x-form.group>

        <x-button onclick="saveSupplierBaru()" class="w-full">
            SIMPAN SUPPLIER
        </x-button>
    </div>
</x-modal>

@push('scripts')
<script>
    async function saveSupplierBaru() {
        const nama = document.getElementById('new_supplier_nama').value.trim();
        const kontak = document.getElementById('new_supplier_kontak').value.trim();
        const alamat = document.getElementById('new_supplier_alamat').value.trim();

        if (!nama) return Swal.fire('Data belum lengkap', 'Nama supplier wajib diisi!', 'warning');
        if (!kontak) return Swal.fire('Data belum lengkap', 'No. telepon / sales wajib diisi!', 'warning');

        try {
            const response = await fetch('{{ url('/supplier/store-ajax') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ nama_supplier: nama, kontak: kontak, alamat: alamat })
            });
            const res = await response.json();

            if (res.status === 'success') {
                const sel = document.getElementById('{{ $selectId }}');
                const opt = new Option(res.data.nama_supplier, res.data.id_supplier, true, true);
                sel.appendChild(opt);
                if (window.jQuery && jQuery(sel).data('select2')) { jQuery(sel).trigger('change'); } else { sel.dispatchEvent(new Event('change', { bubbles: true })); }

                ['new_supplier_nama', 'new_supplier_kontak', 'new_supplier_alamat'].forEach(id => document.getElementById(id).value = '');
                closeModal('supplier-baru');
                Swal.fire('Berhasil', 'Pemasok baru terpilih', 'success');
            } else {
                Swal.fire('Gagal', res.message || 'Data tidak dapat disimpan.', 'error');
            }
        } catch (e) {
            Swal.fire('Gagal menyimpan', 'Supplier belum tersimpan. Periksa jaringan lalu coba lagi.', 'error');
        }
    }
</script>
@endpush
