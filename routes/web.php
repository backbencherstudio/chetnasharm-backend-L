<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Route::redirect('/', '/login');

Route::get('/clear', function () {
    Artisan::call('optimize:clear');
    return "Cleared!";
});


Route::get('/', function () {
    return view('welcome');
});


require __DIR__.'/auth.php';
