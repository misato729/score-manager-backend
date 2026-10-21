<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShopSyncNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'shops',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'shops' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
