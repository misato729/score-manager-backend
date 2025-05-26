<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome'); // resources/views/welcome.blade.php が表示されます
});


Route::middleware(['web', 'auth:sanctum'])->get('/user', function (Request $request) {
    return response()->json($request->user());
});
// web.php の末尾などに追加
require __DIR__.'/auth.php';

