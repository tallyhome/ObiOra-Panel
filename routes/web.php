<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'version' => config('obiora.version'),
        'appName' => config('obiora.name'),
    ]);
})->name('home');
