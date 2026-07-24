<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    use HasUuids;

    protected $fillable = ['wallet_id', 'cc_amount', 'bch_amount', 'bch_address', 'status','txid', 'processed_at'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
