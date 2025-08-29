<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
    ];

    public $timestamps = true;

    public function shop()
{
    return $this->belongsTo(Shop::class, 'shop_id');
}

}

