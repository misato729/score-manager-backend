<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'prefecture_code',
        'lat',
        'lng',
        'price',
        'number_of_machine',
        'description',
        'is_deleted',
    ];

    public function syncState(): HasOne
    {
        return $this->hasOne(ShopSyncState::class);
    }
}
