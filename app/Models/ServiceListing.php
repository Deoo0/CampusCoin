<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceListing extends Model
{
    use HasUuids;

    protected $fillable = ['seller_id', 'title', 'description', 'category', 'price_cc', 'status'];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'listing_id');
    }
}
