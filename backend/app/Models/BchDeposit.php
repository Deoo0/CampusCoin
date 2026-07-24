<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BchDeposit extends Model
{
    use HasUuids;

    protected $fillable = [
        'wallet_id', 'bch_address', 'bch_amount',
        'php_rate_at_deposit', 'cc_credited', 'txid', 'status',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
