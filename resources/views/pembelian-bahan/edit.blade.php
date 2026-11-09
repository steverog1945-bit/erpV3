@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">

    <x-page-header title="Edit Faktur" subtitle="Perbarui informasi administratif pembelian" />

    <form action="{{ route('pembelian-bahan.update', $pembelian->id_pembelian) }}" method="POST">
        @csrf
        @method('PUT')
        @versi($pembelian)

        <x-card class="mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form.group label="No. Faktur Vendor" name="no_faktur" required dalam>
                    <x-form.input type="text" name="no_faktur" value="{{ $pembelian->no_faktur }}" readonly class="opacity-60 cursor-not-allowed" title="Nomor faktur tidak bisa diubah karena dipakai sebagai referensi jurnal akuntansi" />
                </x-form.group>

                <x-form.group label="Tanggal Transaksi" name="tgl_transaksi" required dalam>
                    <x-form.input type="date" name="tgl_transaksi" value="{{ \Carbon\Carbon::parse($pembelian->tgl_transaksi)->format('Y-m-d') }}" required />
                </x-form.group>

                <x-form.group label="Pemasok (Locked)" dalam>
                    <input type="text" value="{{ $pembelian->kedatangan->supplier->nama_supplier ?? 'Umum' }}" readonly
                        class="w-full bg-slate-100 border-none rounded-xl p-3 text-sm font-medium text-slate-400 cursor-not-allowed">
                </x-form.group>

                <x-form.group label="Status Pembayaran" dalam>
                    <div class="p-3 bg-slate-100 rounded-xl text-sm font-medium text-slate-400 flex items-center gap-2 h-[46px]">
                        <i class="fas fa-lock text-[10px]"></i> {{ $pembelian->status_pembayaran }}
                    </div>
                </x-form.group>
            </div>
        </x-card>

        {{-- Info Banner --}}
        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 mb-8 flex gap-4 items-start">
            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
            <div>
                <p class="text-[11px] font-medium text-blue-800 uppercase tracking-wider">Informasi Audit</p>
                <p class="text-xs text-blue-600/80 leading-relaxed font-medium">Rincian item bahan dan harga tidak dapat diubah melalui halaman ini karena sudah tercatat dalam histori stok dan jurnal keuangan. Jika terdapat kesalahan input barang, silakan hapus faktur ini dan buat ulang dari Surat Jalan terkait.</p>
            </div>
        </div>

        <div class="flex justify-between items-center">
            <x-button href="{{ route('pembelian-bahan.index') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Batal
            </x-button>
            <x-button type="submit" class="!px-12 !py-5">
                Simpan Perubahan
            </x-button>
        </div>
    </form>
</div>
@endsection
