<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\WebhookController;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

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
Route::post('/register', [AuthController::class, 'register']);

//google register
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/update-password', [UserController::class, 'updatePass']);
    Route::post('/profile-update', [UserController::class, 'profileUpdate']);
});

Route::prefix('admin')->middleware(['auth:api', 'role:admin'])->group(function () {

    // User Management
    Route::get('/users', [UserController::class, 'data']);
    Route::post('/user-store', [UserController::class, 'store']);
    Route::get('/user-edit-data/{id}', [UserController::class, 'edit']);
    Route::post('/user-update/{id}', [UserController::class, 'update']);
    Route::patch('/user-suspend/{id}', [UserController::class, 'suspend']);

    //Teacher Management
    Route::get('/teachers', [TeacherController::class, 'data']);
    Route::post('/teacher-store', [TeacherController::class, 'store']);
    Route::get('/teacher-edit-data/{id}', [TeacherController::class, 'edit']);
    Route::post('/teacher-update/{id}', [TeacherController::class, 'update']);
    Route::patch('/teacher-suspend/{id}', [TeacherController::class, 'suspend']);

    // Class Management
    Route::get('classes/', [ClassController::class, 'index']);
    Route::post('classes/', [ClassController::class, 'store']);
    Route::get('classes/{id}', [ClassController::class, 'edit']);
    Route::post('classes/{id}', [ClassController::class, 'update']);
    Route::patch('class-status/{id}', [ClassController::class, 'status']);

    // Batch Management
    Route::get('/batches', [BatchController::class, 'index']);
    Route::post('/batches', [BatchController::class, 'store']);
    Route::get('/batches/{id}', [BatchController::class, 'edit']);
    Route::post('/batches/{id}', [BatchController::class, 'update']);
    Route::delete('/batches/{id}', [BatchController::class, 'destroy']);
    Route::patch('/batch-active-status/{id}', [BatchController::class, 'status']);

    Route::get('/class-list', [BatchController::class, 'classList']);
    Route::get('/teacher-list', [BatchController::class, 'teacherList']);
    Route::get('teacher-availablity/by-date', [AvailabilityController::class, 'availabilityByDate']);
    Route::get('teacher-busy-slots', [AvailabilityController::class, 'teacherBusySlots']);


    Route::get('/settings', [SettingController::class, 'show']);
    Route::post('/settings', [SettingController::class, 'update']);

    //payment
    Route::post('/mark-as-paid/{id}', [TransactionController::class, 'markAsPaid']);

});

Route::middleware(['auth:api', 'role:admin|teacher'])->group(function () {

    Route::get('/class-time', [SettingController::class, 'getClassTime']);
    Route::get('teacher-availability', [AvailabilityController::class, 'index']);
    Route::post('teacher-availability', [AvailabilityController::class, 'store']);
    Route::get('teacher-availability/edit', [AvailabilityController::class, 'edit']);
    Route::post('teacher-availability/update', [AvailabilityController::class, 'update']);
    Route::delete('teacher-availability/{id}', [AvailabilityController::class, 'destroy']);

    Route::get('teachers-schedule', [AvailabilityController::class, 'teacherSchedule']);

});

Route::prefix('teacher')->middleware(['auth:api', 'role:teacher'])->group(function () {

});

Route::prefix('student')->middleware(['auth:api', 'role:student'])->group(function () {

    Route::post('create-payment', [PaymentController::class, 'createPayment']);

});

Route::middleware(['auth:api', 'role:admin|student'])->group(function () {

    Route::get('/payments', [TransactionController::class, 'index']);

});

Route::post('/stripe/webhook', [WebhookController::class, 'stripeWebhook']);
