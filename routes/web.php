<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkController;

Route::get('/', function () { return view('home'); })->name('home');

Route::get('/links', function () { return view('home'); });

Route::post('/links', [LinkController::class, 'processLinks'])->name('links');

Route::put('/links', [LinkController::class, 'processLinks'])->name('refresh');