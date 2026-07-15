<?php

use App\Http\Controllers\Api\Base\BaseController;
use App\Http\Controllers\Admin\LangController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/change-language/{lang}', [LangController::class, 'change'])
    ->middleware('web')
    ->name('change-language');

Route::get('/health/', [BaseController::class, 'health']);
Route::get('/health', [BaseController::class, 'health']);
