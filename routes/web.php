<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OutgoingEmailController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');
    Route::get('register', [RegistrationController::class, 'create'])->name('register');
    Route::post('register', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});
Route::middleware(['auth', 'nocache'])->group(function () {
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:2,1')->name('verification.send');
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
});
Route::middleware(['auth', 'nocache', 'verified'])->group(function () {
    Route::get('settings/email', [EmailSettingsController::class, 'edit'])->name('email.edit');
    Route::put('settings/email', [EmailSettingsController::class, 'update'])->name('email.update');
    Route::get('emails', [OutgoingEmailController::class, 'index'])->name('emails.index');
    Route::get('emails/create', [OutgoingEmailController::class, 'create'])->name('emails.create');
    Route::post('emails', [OutgoingEmailController::class, 'store'])->middleware('throttle:mail-delivery')->name('emails.store');
    Route::post('sales/{sale}/email', [OutgoingEmailController::class, 'receipt'])->middleware('throttle:mail-delivery')->name('sales.email');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resources(['products' => ProductController::class, 'customers' => CustomerController::class, 'staff' => StaffController::class], ['except' => ['show']]);
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('media/{name}', [MediaController::class, 'show'])->where('name', '[a-zA-Z0-9-]+\.jpg')->name('media');
});
