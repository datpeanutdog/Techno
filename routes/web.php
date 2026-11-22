<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\SupportReportController as AdminSupportReportController;
use App\Http\Controllers\Admin\SuspensionAppealController as AdminSuspensionAppealController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SupportReportController;
use App\Http\Controllers\SuspensionAppealController;
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

Route::get('/', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/support', [SupportReportController::class, 'index'])->name('support.reports.index');
Route::post('/support', [SupportReportController::class, 'storeContact'])
    ->middleware('throttle:5,1')
    ->name('support.reports.contact.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/sellers/{user}', [SellerController::class, 'show'])->name('sellers.show');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
    Route::get('/account/suspension', [SuspensionAppealController::class, 'show'])->name('account.suspension.show');
    Route::post('/account/suspension/appeals', [SuspensionAppealController::class, 'store'])
        ->middleware('throttle:5,1,counter-appeal-v2:')
        ->name('account.suspension.appeals.store');
    Route::get('/account/suspension/appeals/{suspensionAppeal}/proof', [SuspensionAppealController::class, 'proof'])
        ->name('account.suspension.appeals.proof');
});

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::prefix('/admin/users')
        ->name('admin.users.')
        ->middleware('can:manage-users')
        ->group(function (): void {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])->name('role.update');
        });

    Route::post('/sellers/{user}/reviews', [SellerController::class, 'review'])
        ->middleware('throttle:5,1')
        ->name('sellers.reviews.store');
    Route::get('/profile', [AccountController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/settings', [AccountController::class, 'editSettings'])->name('settings.index');
    Route::post('/saved-listings/{listing}', [MarketplaceController::class, 'saveListing'])
        ->name('saved-listings.store');
    Route::delete('/saved-listings/{listing}', [MarketplaceController::class, 'unsaveListing'])
        ->name('saved-listings.destroy');
    Route::get('/my-listings', [MarketplaceController::class, 'myListings'])->name('my-listings');
    Route::get('/my-listings/{listing}/edit', [MarketplaceController::class, 'edit'])->name('my-listings.edit');
    Route::put('/my-listings/{listing}', [MarketplaceController::class, 'update'])->name('my-listings.update');
    Route::delete('/my-listings/{listing}', [MarketplaceController::class, 'destroy'])->name('my-listings.destroy');
    Route::get('/listings/create', [MarketplaceController::class, 'create'])->name('listings.create');
    Route::post('/listings', [MarketplaceController::class, 'store'])->middleware('throttle:10,1')->name('listings.store');
    Route::get('/messages', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/messages/{conversation}/updates', [MessageController::class, 'updates'])
        ->middleware('throttle:60,1')
        ->name('conversations.messages.updates');
    Route::get('/listings/{listing:slug}/message', [MarketplaceController::class, 'messageSeller'])
        ->name('listings.messages.create');
    Route::post('/listings/{listing:slug}/messages', [ConversationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('listings.messages.store');
    Route::post('/messages/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('conversations.messages.store');
    Route::get('/support/reports', [SupportReportController::class, 'create'])->name('support.reports.create');
    Route::post('/support/reports', [SupportReportController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('support.reports.store');
    Route::get('/support/reports/mine', [SupportReportController::class, 'mine'])->name('support.reports.mine');
    Route::get('/support/reports/{supportReport}/proofs/{index}', [SupportReportController::class, 'proof'])
        ->whereNumber('index')
        ->name('support.reports.proofs');

    Route::prefix('/admin/support-reports')
        ->name('admin.support-reports.')
        ->middleware('can:manage-support')
        ->group(function (): void {
            Route::get('/', [AdminSupportReportController::class, 'index'])->name('index');
            Route::get('/{supportReport}', [AdminSupportReportController::class, 'show'])->name('show');
            Route::patch('/{supportReport}', [AdminSupportReportController::class, 'update'])->name('update');
            Route::post('/{supportReport}/suspend', [AdminSupportReportController::class, 'suspend'])
                ->name('suspend');
            Route::post('/{supportReport}/restore', [AdminSupportReportController::class, 'restore'])
                ->name('restore');
        });

    Route::prefix('/admin/suspension-appeals')
        ->name('admin.suspension-appeals.')
        ->middleware('can:manage-support')
        ->group(function (): void {
            Route::get('/', [AdminSuspensionAppealController::class, 'index'])->name('index');
            Route::get('/{suspensionAppeal}/proof', [AdminSuspensionAppealController::class, 'proof'])->name('proof');
            Route::get('/{suspensionAppeal}', [AdminSuspensionAppealController::class, 'show'])->name('show');
            Route::patch('/{suspensionAppeal}', [AdminSuspensionAppealController::class, 'update'])
                ->name('update');
        });
});

Route::get('/listings/{listing:slug}', [MarketplaceController::class, 'show'])
    ->middleware('auth')
    ->name('listings.show');
