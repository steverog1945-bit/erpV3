<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use Auditable;

    protected $table = 'accounting_periods';

    protected $fillable = ['tahun', 'bulan', 'status', 'closed_by', 'closed_at', 'catatan'];

    protected $casts = [
        'closed_at' => 'datetime',
    ];
}
