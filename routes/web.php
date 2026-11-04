<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\MarketplaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AccountController::class, 'registerForm'])->name('register');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/login', [AccountController::class, 'loginForm'])->name('login');
    Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [AccountController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'sendPasswordResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AccountController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
    Route::get('/profile', [AccountController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/my-listings', [MarketplaceController::class, 'myListings'])->name('my-listings');
    Route::get('/my-listings/{listing}/edit', [MarketplaceController::class, 'edit'])->name('my-listings.edit');
    Route::put('/my-listings/{listing}', [MarketplaceController::class, 'update'])->name('my-listings.update');
    Route::delete('/my-listings/{listing}', [MarketplaceController::class, 'destroy'])->name('my-listings.destroy');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/listings/create', [MarketplaceController::class, 'create'])->name('listings.create');
    Route::post('/listings', [MarketplaceController::class, 'store'])->middleware('throttle:10,1')->name('listings.store');
});
Route::get('/listings/{listing:slug}', [MarketplaceController::class, 'show'])
    ->middleware('auth')->name('listings.show');
