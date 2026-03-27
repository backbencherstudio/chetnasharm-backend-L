<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotPasswordController;


Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Please login to continue',
    ], 401);
})->name('login');

Route::post('/login', [AuthController::class, 'login']);

Route::post('/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/password-reset', [ForgotPasswordController::class, 'resetPassword']);
// Route::post('/register', [AuthController::class, 'register']);

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('auth:api')->post('/update-password', [UserController::class, 'updatePass']);
    Route::middleware('auth:api')->put('/profile-update', [UserController::class, 'profileUpdate']);
});

Route::prefix('admin')->middleware(['auth:api', 'role:admin'])->group(function () {

    Route::get('/users', [UserController::class, 'data']);
    Route::post('/user-store', [UserController::class, 'store']);

    Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
    Route::put('/user-update/{id}', [UserController::class, 'update']);



    Route::delete('/user-delete/{id}', [UserController::class, 'destroy']);
});

Route::middleware(['auth:api', 'role:admin|teacher'])->group(function () {

});

Route::prefix('teacher')->middleware(['auth:api', 'role:teacher'])->group(function () {

});


Route::prefix('student')->middleware(['auth:api', 'role:student'])->group(function () {

});
