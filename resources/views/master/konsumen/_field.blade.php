{{-- Isian konsumen yang sama untuk modal Tambah dan Ubah. $awalan membedakan id elemen (kosong atau "edit_"). --}}
<x-form.group label="Nama lengkap" name="nama_konsumen" required dalam>
    <x-form.input type="text" name="nama_konsumen" id="{{ $awalan }}nama" required maxlength="100" />
    @if(!$awalan)
        <div id="cekNamaWrap" class="cek-nama-hint hidden" role="status" aria-live="polite"></div>
    @endif
</x-form.group>

<div class="grid grid-cols-2 gap-4">
    <x-form.group label="Telepon/WA" name="telepon" required dalam>
        <x-form.input type="text" name="telepon" id="{{ $awalan }}telepon" required maxlength="20" inputmode="tel" />
    </x-form.group>
    <x-form.group label="Golongan" name="id_golongan" required dalam>
        <x-form.select name="id_golongan" id="{{ $awalan }}golongan" required>
            @foreach($golongan as $g)
                <option value="{{ $g->id_golongan }}">{{ $g->nama_golongan }}</option>
            @endforeach
        </x-form.select>
    </x-form.group>
</div>

<x-form.group label="Tipe konsumen" name="tipe_konsumen" dalam>
    <x-form.select name="tipe_konsumen" id="{{ $awalan }}tipe_konsumen">
        <option value="temporary">Guest (sekali pesan)</option>
        <option value="permanent">Member (pelanggan tetap)</option>
    </x-form.select>
</x-form.group>

<x-form.group label="Alamat" name="alamat" dalam>
    <textarea name="alamat" id="{{ $awalan }}alamat" rows="2"></textarea>
</x-form.group>

@if(!$awalan)
<script>
// Cek duplikat nama sebelum menyimpan konsumen baru. "Pakai yang lama"
// membawa operator ke daftar konsumen terfilter ke kode yang dipilih.
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('nama');
    var hint = document.getElementById('cekNamaWrap');
    if (!input || !hint || !window.cekKonsumenMirip) return;
    window.cekKonsumenMirip({
        input: input,
        hint: hint,
        url: '{{ url('/konsumen/cek-nama') }}',
        pilih: function (k) {
            window.location.href = '{{ route('konsumen.index') }}?search=' + encodeURIComponent(k.kode);
        }
    });
});
</script>
@endif
