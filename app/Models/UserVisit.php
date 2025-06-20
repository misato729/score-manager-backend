<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVisit extends Model
{

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

