<?php


use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ClassRecordingController;
use App\Http\Controllers\Api\TeacherNoteController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Api\DashboardController;


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

Route::get('/classes', [ClassController::class, 'landClass']);
Route::get('/teachers', [TeacherController::class, 'landTeacher']);

Route::get('single-class/{classId}', [ClassController::class,'singleClass']);
Route::get('/batches/{classId}', [ClassController::class, 'landBatch']);
Route::get('single-batch/{batchId}', [ClassController::class, 'singleBatch']);

Route::get('/support', [SettingController::class, 'support']);

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

    Route::get('/batches-by-class/{classId}', [BatchController::class, 'getBatchesByClass']);
    Route::post('/change-batch', [EnrollmentController::class, 'changeBatch']);
    // Waitlist
    Route::get('/waiting-list', [WaitlistController::class, 'getForAdmin']);

    Route::get('/class-list', [BatchController::class, 'classList']);
    Route::get('/teacher-list', [BatchController::class, 'teacherList']);
    Route::get('teacher-availablity/by-date', [AvailabilityController::class, 'availabilityByDate']);
    Route::get('teacher-busy-slots', [AvailabilityController::class, 'teacherBusySlots']);

    Route::get('/settings', [SettingController::class, 'show']);
    Route::post('/settings', [SettingController::class, 'update']);

    Route::get('/notification-logs', [SettingController::class, 'logs']);

    //payment
    Route::post('/mark-as-paid/{id}', [TransactionController::class, 'markAsPaid']);

    //dashboard
    Route::get('total-student-per-month', [DashboardController::class,'totalStudentMonthly']);
    Route::get('total-enrollment-per-month', [DashboardController::class,'totalEnrollmentMonthly']);

});

Route::middleware(['auth:api', 'role:admin|teacher'])->group(function () {

    Route::get('/class-time', [SettingController::class, 'getClassTime']);
    Route::get('teacher-availability', [AvailabilityController::class, 'index']);
    Route::post('teacher-availability', [AvailabilityController::class, 'store']);
    Route::get('teacher-availability/edit', [AvailabilityController::class, 'edit']);
    Route::post('teacher-availability/update', [AvailabilityController::class, 'update']);
    Route::delete('teacher-availability/{id}', [AvailabilityController::class, 'destroy']);

    Route::get('teachers-schedule', [AvailabilityController::class, 'teacherSchedule']);

    Route::get('/enrollments/{batchId}', [EnrollmentController::class, 'getEnrollmentsByBatch']);

    Route::get('attendance-monthly/{batchId}', [AttendanceController::class, 'getMonthlyAttendance']);
    Route::get('attendances/{batchId}', [AttendanceController::class, 'getAttendanceSheet']);
    Route::post('attendance-save', [AttendanceController::class, 'store']);
    Route::post('attendance-single', [AttendanceController::class, 'updateSingle']);

    Route::patch('update-zoom-link/{batchId}', [BatchController::class, 'updateZoomLink']);

});

Route::prefix('teacher')->middleware(['auth:api', 'role:teacher'])->group(function () {

    Route::get('/batches', [BatchController::class, 'teacherBatch']);
    Route::get('/single-batch/{batchId}', [BatchController::class, 'singleBatch']);

    Route::get('/recordings/{batchId}', [ClassRecordingController::class, 'index']);
    Route::post('/recordings', [ClassRecordingController::class, 'store']);
    Route::get('/edit-recording/{id}', [ClassRecordingController::class, 'show']);
    Route::post('/recordings/{id}', [ClassRecordingController::class, 'update']);
    Route::delete('/recordings/{id}', [ClassRecordingController::class, 'destroy']);


    Route::get('/notes/{batch_id}', [TeacherNoteController::class, 'index']);
    Route::post('/notes', [TeacherNoteController::class, 'store']);
    Route::get('/notes-edit/{id}', [TeacherNoteController::class, 'show']);
    Route::post('/notes/{id}', [TeacherNoteController::class, 'update']);
    Route::delete('/notes/{id}', [TeacherNoteController::class, 'destroy']);

});

Route::prefix('student')->middleware(['auth:api', 'role:student'])->group(function () {

    Route::post('create-payment', [PaymentController::class, 'createPayment']);

    //Whatsapp
    // Route::post('whatsapp-number', [UserController::class, 'updateWhatsapp']);

    Route::get('/waiting-list', [WaitlistController::class, 'getForUser']);
    Route::post('/waiting-list', [WaitlistController::class, 'store']);
    // Route::delete('/waitlist/{batchId}', [WaitlistController::class, 'destroy']);

    Route::get('/batches', [BatchController::class, 'studentBatch']);
    Route::get('/single-batch/{batchId}', [BatchController::class, 'singleBatch']);
    Route::get('/recordings/{batchId}', [ClassRecordingController::class, 'forStudent']);

    Route::get('/notes/{batch_id}', [TeacherNoteController::class, 'forStudent']);

});

Route::middleware(['auth:api', 'role:admin|student'])->group(function () {

    Route::get('/payments', [TransactionController::class, 'index']);

});

Route::post('/stripe/webhook', [WebhookController::class, 'stripeWebhook']);

Route::get('paypal-success', [PaymentController::class, 'paypalCapture'])->name('paypal.capture');
Route::get('paypal-cancel', [PaymentController::class, 'paypalCancel']);