<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    use HasFactory;

    public const JIRIKI_RANKS = [
        'S+',
        'S',
        'A+',
        'A',
        'B+',
        'B',
        'C',
        'D',
        'E',
        'F',
    ];

    protected $fillable = [
        'title',
        'jiriki_rank',
    ];
}
