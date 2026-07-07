<?php

namespace App\Http\Controllers;

use App\Models\Song;

class SongController extends Controller
{
    public function index()
    {
        return response()->json(Song::orderBy('id')->get());
    }
}
