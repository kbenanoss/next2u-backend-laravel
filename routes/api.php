<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ApiController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// Authentication routes

Route::get('/test', function () {
    return response()->json(['message' => 'Connection successful!']);
});

// User Registration
Route::post('/register', [AuthController::class, 'register']);

// User Login
Route::post('/login', [AuthController::class, 'login']);

// Get User Profile
Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'profile']);

// User Logout
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Verify OTP
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// Email Verification (for API, not web-based)
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

// Resend Verification Email
Route::middleware('auth:sanctum')->post('/email/resend', [AuthController::class, 'resendVerificationEmail']);

// Send Password Reset Link
Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail']);

// Reset Password
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Fetch all roles
Route::get('/roles', [AuthController::class, 'getRoles']);


// Change Password
Route::middleware('auth:sanctum')->post('/password/change', [AuthController::class, 'changePassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('subscriptions', SubscriptionController::class);
    });

    Route::middleware(['role:shop_owner,agent'])->group(function () {
        Route::apiResource('shops', ShopController::class);
    });

    Route::post('pay', [PaymentController::class, 'redirectToGateway'])->name('pay');
    Route::get('payment/callback', [PaymentController::class, 'handleGatewayCallback']);
});

// Public routes
Route::get('categories', [CategoryController::class, 'index']);
Route::get('shops', [ShopController::class, 'index']);
