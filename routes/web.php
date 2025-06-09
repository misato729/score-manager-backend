<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'csrf token set']);
});

Route::get('/', function () {
    return 'Laravel is alive! 🎉';
});


Route::middleware(['web', 'auth:sanctum'])->get('/user', function (Request $request) {
    return response()->json($request->user());
});
// web.php の末尾などに追加
require __DIR__.'/auth.php';

