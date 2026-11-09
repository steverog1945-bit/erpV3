<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JerseyOrder extends Model
{
    use Auditable;

    protected $table = 'jersey_orders';

    protected $fillable = [
        'order_number',
        'order_date',
        'id_konsumen',
        'customer_name',
        'customer_phone',
        'team_name',
        'deadline',
        'status',
        'jersey_designer_id',
        'designer_name',
        'id_desainer',
        'file_siap_cetak',
        'notes',
        'total',
        'discount',
        'additional_charge',
        'deposit',
        'status_pembayaran',
        'catatan_referensi_transaksi',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'deadline' => 'date',
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'additional_charge' => 'decimal:2',
        'deposit' => 'decimal:2',
        'file_siap_cetak' => 'boolean',
    ];

    public function konsumen()
    {
        return $this->belongsTo(Konsumen::class, 'id_konsumen', 'id_konsumen');
    }

    public function designer()
    {
        return $this->belongsTo(JerseyDesigner::class, 'jersey_designer_id');
    }

    public function desainer()
    {
        return $this->belongsTo(User::class, 'id_desainer', 'id_user');
    }

    public function lines()
    {
        return $this->hasMany(JerseyOrderLine::class, 'jersey_order_id');
    }

    public function payments()
    {
        return $this->hasMany(JerseyOrderPayment::class, 'jersey_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }

    /**
     * Total order = jumlah subtotal semua line + biaya tambahan - diskon -
     * dipanggil ulang tiap line berubah (tambah/edit/hapus) supaya total
     * order selalu konsisten dengan rincian per-jenis-barangnya.
     */
    public function recalculateTotal(): void
    {
        // lockForUpdate() pada baris order sendiri sebelum hitung ulang -
        // tanpa ini, dua panggilan nyaris bersamaan (mis. dua baris order
        // ditambah/dihapus beruntun) bisa saling menimpa hasil hitungan
        // total/status pembayaran satu sama lain (lost update pada kolom
        // turunan). DB::transaction() aman di-nest via SAVEPOINT dengan
        // recalculatePembayaran() di bawah, jadi tidak mengubah perilaku
        // saat dipanggil standalone dari JerseyFinanceController.
        DB::transaction(function () {
            DB::table('jersey_orders')->where('id', $this->id)->lockForUpdate()->first();
            $this->refresh();

            $subtotalLines = (float) $this->lines()->sum('subtotal');
            // Finishing per baris (mis. jahit keliling per keping) masuk total -
            // dulu finishing jersey tidak punya tempat, biaya jahit tidak pernah
            // bisa dicatat. SEMUA status finishing dihitung (Standar dari rumus
            // server, Custom oleh operator berhak) - pola sama dengan POS yang
            // menjumlahkan biaya finishing apapun statusnya ke subtotal baris.
            $finishingTotal = \Illuminate\Support\Facades\DB::table('jersey_line_finishing')
                ->whereIn('jersey_order_line_id', $this->lines()->pluck('id'))
                ->sum('biaya');
            $total = max(0, $subtotalLines + $finishingTotal - (float) $this->discount + (float) $this->additional_charge);

            // Diskon, kurangi barang, atau hapus barang tidak boleh membuat total di bawah uang yang sudah diterima:
            // piutang jadi minus, kelebihan bayar tidak terlihat (status tetap Lunas), dan tidak ada jalur pengembalian.
            $sudahDibayar = (float) $this->payments()->sum('jumlah');
            if ($total < $sudahDibayar - 0.01) {
                throw new \RuntimeException('Total order baru (Rp ' . number_format($total, 0, ',', '.') . ') lebih kecil dari pembayaran yang sudah diterima (Rp '
                    . number_format($sudahDibayar, 0, ',', '.') . '). Batalkan dulu pembayaran yang berlebih lewat void pembayaran (perlu persetujuan), lalu ubah ordernya.');
            }

            $this->update(['total' => $total]);
            \App\Services\JerseyAccountingService::sinkronPendapatan($this);
            $this->recalculatePembayaran();
        });
    }

    /**
     * deposit & status_pembayaran BUKAN lagi diisi manual - keduanya
     * turunan dari jumlah baris jersey_order_payments (ledger Keuangan
     * Jersey, terpisah dari kas Kasir utama). Dipanggil ulang tiap ada
     * pembayaran ditambah/dihapus, dan tiap total order berubah (lihat
     * recalculateTotal() di atas) supaya status Lunas/DP tetap akurat
     * kalau total order berubah setelah sebagian sudah dibayar.
     */
    public function recalculatePembayaran(): void
    {
        // lockForUpdate() sendiri juga (bukan cuma mengandalkan caller) -
        // dipanggil standalone dari JerseyFinanceController::storePayment/
        // destroyPayment tiap ada pembayaran ditambah/dihapus, jadi dua
        // pembayaran nyaris bersamaan untuk order yang sama perlu dijaga
        // di sini juga, bukan cuma lewat recalculateTotal().
        DB::transaction(function () {
            DB::table('jersey_orders')->where('id', $this->id)->lockForUpdate()->first();
            $this->refresh();

            $totalDibayar = (float) $this->payments()->sum('jumlah');
            $total = (float) $this->total;

            $status = 'Belum Bayar';
            if ($totalDibayar > 0 && $totalDibayar < $total) {
                $status = 'DP';
            } elseif ($totalDibayar > 0 && $totalDibayar >= $total) {
                $status = 'Lunas';
            }

            $this->update(['deposit' => $totalDibayar, 'status_pembayaran' => $status]);
        });
    }

    public function getSisaTagihanAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->deposit);
    }

    /**
     * Piutang jatuh tempo (lewat deadline, belum lunas) - dipakai untuk
     * pengingat di dashboard Keuangan Jersey & badge di daftar order.
     */
    public function getJatuhTempoAttribute(): bool
    {
        return $this->status_pembayaran !== 'Lunas'
            && $this->deadline
            && $this->deadline->isPast();
    }

    public static function generateOrderNumber(\DateTimeInterface $date): string
    {
        $prefix = 'JO-' . $date->format('Ymd') . '-';

        // Audit Order Jersey (duplicate order number / concurrency): kunci
        // SEMUA nomor terpakai untuk prefix hari ini (bukan cuma MAX satu
        // nilai) - dua order nyaris bersamaan dulu bisa membaca MAX yang
        // sama sebelum salah satu commit, menghasilkan nomor DOBEL. Dengan
        // mem-pluck daftar terkunci di dalam transaksi pemanggil, request
        // kedua menunggu sampai request pertama commit, lalu membaca MAX
        // yang sudah termasuk order baru itu. Nomor SELALU monoton naik
        // (MAX+1): slot bekas order yang dihapus TIDAK dipakai ulang supaya
        // nomor yang sama tidak pernah menunjuk dua histori berbeda.
        // unique() order_number di database = jaring pengaman terakhir.
        $terpakai = DB::table('jersey_orders')
            ->where('order_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('order_number');

        // Ambil angka setelah tanda '-' TERAKHIR (bukan substr(-3)) supaya
        // tetap benar bila urutan menembus 999 (JO-...-1000).
        $next = ((int) $terpakai->map(fn ($no) => (int) \Illuminate\Support\Str::afterLast($no, '-'))->max()) + 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
