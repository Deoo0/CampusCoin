<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CcTransaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'from_wallet_id', 'to_wallet_id', 'amount',
        'type', 'reference_id', 'reference_type', 'created_at',
    ];

    protected static function booted()
    {
        static::creating(function ($tx) {
            $tx->created_at ??= now();
        });
    }
}
