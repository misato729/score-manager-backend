<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSyncState extends Model
{
    protected $primaryKey = 'shop_id';

    public $incrementing = false;

    protected $fillable = [
        'shop_id',
        'last_checked_on',
        'consecutive_missing_days',
        'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_on' => 'immutable_date',
            'consecutive_missing_days' => 'integer',
            'hidden_at' => 'immutable_datetime',
        ];
    }
}
