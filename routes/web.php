<?php

use App\Http\Controllers\Web\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('app.home');
Route::get('/kategorija/{category:slug}', [AppController::class, 'category'])->name('app.category');
Route::get('/kategorija/{category:slug}/{city}', [AppController::class, 'search'])
    ->where('city', '.*')
    ->name('app.search');
Route::get('/o-nama', [AppController::class, 'about'])->name('app.about');
