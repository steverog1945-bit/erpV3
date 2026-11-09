@extends('layouts.app')

@section('content')
<div class="card p-4 shadow-sm">
    <h4 class="mb-3">{{ isset($kedatangan) ? 'Edit Kedatangan Bahan' : 'Tambah Kedatangan Bahan' }}</h4>

    <form action="{{ isset($kedatangan) ? route('kedatangan-bahan.update', $kedatangan->id_kedatangan) : route('kedatangan-bahan.store') }}" method="POST">
        @csrf
        @if(isset($kedatangan))
            @method('PUT')
        @endif

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">No Surat Jalan</label>
                <input type="text" name="no_surat_jalan" class="form-control" value="{{ old('no_surat_jalan', $kedatangan->no_surat_jalan ?? '') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tanggal Datang</label>
                <input type="date" name="tgl_datang" class="form-control" value="{{ old('tgl_datang', isset($kedatangan) ? $kedatangan->tgl_datang : date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Pemasok</label>
                <select name="id_supplier" class="form-select" required>
                    <option value="">-- Pilih Supplier --</option>
                    @foreach($supplier as $s)
                        <option value="{{ $s->id_supplier }}" {{ isset($kedatangan) && $kedatangan->id_supplier == $s->id_supplier ? 'selected' : '' }}>{{ $s->nama_supplier }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- DETAIL BAHAN --}}
        <h5 class="mt-3">Detail Bahan</h5>
        <div class="table-responsive">
            <table class="table table-bordered" id="detail-bahan-table">
                <thead class="table-light">
                    <tr>
                        <th>Nama Bahan</th>
                        <th>Qty</th>
                        <th>Satuan</th>
                        <th width="50px"><button type="button" class="btn btn-sm btn-success" id="add-row">+</button></th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($kedatangan))
                        @foreach($kedatangan->detail as $d)
                        <tr>
                            <td>
                                <select name="id_bahan[]" class="form-select" required>
                                    <option value="">-- Pilih Bahan --</option>
                                    @foreach($bahan as $b)
                                        <option value="{{ $b->id_bahan }}" {{ $d->id_bahan == $b->id_bahan ? 'selected' : '' }}>{{ $b->nama_bahan }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="qty[]" class="form-control" value="{{ angka_polos($d->qty, 4) }}" required></td>
                            <td><input type="text" name="satuan[]" class="form-control" value="{{ $d->satuan }}" required></td>
                            <td><button type="button" class="btn btn-sm btn-danger remove-row">-</button></td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>
                                <select name="id_bahan[]" class="form-select" required>
                                    <option value="">-- Pilih Bahan --</option>
                                    @foreach($bahan as $b)
                                        <option value="{{ $b->id_bahan }}">{{ $b->nama_bahan }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="qty[]" class="form-control" required></td>
                            <td><input type="text" name="satuan[]" class="form-control" required></td>
                            <td><button type="button" class="btn btn-sm btn-danger remove-row">-</button></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary mt-3">{{ isset($kedatangan) ? 'Update' : 'Simpan' }}</button>
        <a href="{{ route('kedatangan-bahan.index') }}" class="btn btn-secondary mt-3">Kembali</a>
    </form>
</div>

@section('scripts')
<script>
    // Tambah baris baru
    document.getElementById('add-row').addEventListener('click', function(){
        let table = document.getElementById('detail-bahan-table').getElementsByTagName('tbody')[0];
        let newRow = table.insertRow();
        newRow.innerHTML = `
            <td>
                <select name="id_bahan[]" class="form-select" required>
                    <option value="">-- Pilih Bahan --</option>
                    @foreach($bahan as $b)
                        <option value="{{ $b->id_bahan }}">{{ $b->nama_bahan }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" step="0.01" min="0.01" name="qty[]" class="form-control" required></td>
            <td><input type="text" name="satuan[]" class="form-control" required></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-row">-</button></td>
        `;
    });

    // Hapus baris
    document.addEventListener('click', function(e){
        if(e.target && e.target.classList.contains('remove-row')){
            e.target.closest('tr').remove();
        }
    });
</script>
@endsection

@endsection