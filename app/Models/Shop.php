<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'lat',
        'lng',
        'price',
        'number_of_machine',
        'description',
        'is_deleted',
    ];
}
