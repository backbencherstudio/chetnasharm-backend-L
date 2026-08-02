<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    Artisan::call('optimize:clear');

    return 'Cleared!';
});

Route::get('/', function () {
    return view('welcome');
});
