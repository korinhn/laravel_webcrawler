<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkController;

Route::get('/', function () { return view('home'); })->name('home');

Route::get('/links', [LinkController::class, 'processLinks'])->name('getlinks');

Route::post('/links', [LinkController::class, 'processLinks'])->name('links');

Route::put('/links', [LinkController::class, 'processLinks'])->name('refresh');