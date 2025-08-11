<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index()
    {
        $shops = Shop::all();
        return Shop::select(
            'id',
            'name',
            'address',
            'lat',
            'lng',
            'price',
            'number_of_machine',
            'description'
        )
        ->where('is_deleted', false)
        ->get();
        return view('shops', compact('shops'));

    }

}

