<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
});

require __DIR__.'/superadmin.php';
require __DIR__.'/core.php';
require __DIR__.'/stock.php';
require __DIR__.'/appointments.php';
require __DIR__.'/auth.php';
