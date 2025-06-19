<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVisit extends Model
{
    protected $table = 'user_visit';

    protected $fillable = [
        'user_id',
        'shop_id',
        'visited_at',
    ];

    public $timestamps = true;
}
