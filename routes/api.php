<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FetchRequestController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SupportReportController;
use App\Http\Controllers\Api\SuspensionAppealController;
use App\Http\Controllers\PresenceController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AccountController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/login', [AccountController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/forgot-password', [AccountController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AccountController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/auth/suspension/login', [AccountController::class, 'suspensionLogin'])->middleware('throttle:10,1');
Route::post('/support/contact', [SupportReportController::class, 'storeContact'])->middleware('throttle:5,1');
Route::middleware(['auth:sanctum', 'abilities:suspension-appeal'])->group(function (): void {
    Route::get('/auth/suspension/me', [SuspensionAppealController::class, 'show']);
    Route::post('/auth/suspension/logout', [AccountController::class, 'logout']);
    Route::post('/auth/suspension/appeals', [SuspensionAppealController::class, 'store'])
        ->middleware('throttle:5,1');
});
Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat'])
        ->middleware('throttle:60,1');
    Route::get('/auth/me', [AccountController::class, 'me']);
    Route::post('/auth/logout', [AccountController::class, 'logout']);
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{slug}', [ListingController::class, 'show']);
    Route::get('/sellers/{user}', [SellerController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])
        ->middleware('throttle:30,1');
    Route::post('/listings/{listing}/messages', [ConversationController::class, 'storeForListing'])
        ->middleware('throttle:20,1');
    Route::post('/sellers/{user}/reviews', [SellerController::class, 'review'])->middleware('throttle:5,1');
    Route::put('/auth/profile', [AccountController::class, 'updateProfile']);
    Route::get('/my-listings', [ListingController::class, 'mine']);
    Route::get('/my-listings/{id}', [ListingController::class, 'owned']);
    Route::put('/my-listings/{id}', [ListingController::class, 'update']);
    Route::delete('/my-listings/{id}', [ListingController::class, 'destroy']);
    Route::post('/listings', [ListingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/saved-listings', [ListingController::class, 'saved']);
    Route::post('/saved-listings/{listing}', [ListingController::class, 'save']);
    Route::delete('/saved-listings/{listing}', [ListingController::class, 'unsave']);
    Route::get('/support/reports', [SupportReportController::class, 'index']);
    Route::post('/support/reports', [SupportReportController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::get('/fetch/requests', [FetchRequestController::class, 'index']);
    Route::get('/fetch/requests/mine', [FetchRequestController::class, 'mine']);
    Route::post('/fetch/requests', [FetchRequestController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::post('/fetch/requests/{fetchRequest}/accept', [FetchRequestController::class, 'accept'])
        ->middleware('throttle:10,1');
    Route::post('/fetch/requests/{fetchRequest}/delivered', [FetchRequestController::class, 'delivered'])
        ->middleware('throttle:10,1');
    Route::post('/fetch/requests/{fetchRequest}/confirm', [FetchRequestController::class, 'confirm'])
        ->middleware('throttle:10,1');
    Route::get('/fetch/requests/updates', [FetchRequestController::class, 'updates'])
        ->middleware('throttle:30,1');
});

Route::middleware(['auth:sanctum', 'account.active', 'can:manage-users'])->prefix('/admin')->group(function (): void {
    Route::get('/users', [AdminController::class, 'users']);
    Route::patch('/users/{user}/role', [AdminController::class, 'updateRole']);
});

Route::middleware(['auth:sanctum', 'account.active', 'can:manage-support'])->prefix('/admin')->group(function (): void {
    Route::get('/support-reports', [AdminController::class, 'supportReports']);
    Route::get('/support-reports/{supportReport}', [AdminController::class, 'supportReport']);
    Route::patch('/support-reports/{supportReport}', [AdminController::class, 'updateSupportReport']);
    Route::post('/support-reports/{supportReport}/suspend', [AdminController::class, 'suspendReportedUser']);
    Route::post('/support-reports/{supportReport}/restore', [AdminController::class, 'restoreReportedUser']);
    Route::get('/suspension-appeals', [AdminController::class, 'suspensionAppeals']);
    Route::get('/suspension-appeals/{suspensionAppeal}', [AdminController::class, 'suspensionAppeal']);
    Route::patch('/suspension-appeals/{suspensionAppeal}', [AdminController::class, 'updateSuspensionAppeal']);
});
