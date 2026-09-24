<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/auth/login-redirect', [AuthController::class, 'loginRedirect']);
Route::get('/auth/callback', [AuthController::class, 'callback']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-session', function () {
    return response()->json([
        'session_id' => session()->getId(),
        'has_access_token' => session()->has('access_token'),
        'all_keys' => array_keys(session()->all()),
        'cookie_header' => request()->header('cookie'),
    ]);
});
