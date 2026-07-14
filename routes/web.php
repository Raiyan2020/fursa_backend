<?php

use App\Http\Controllers\Api\Base\BaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/', [BaseController::class, 'health']);
Route::get('/health', [BaseController::class, 'health']);
