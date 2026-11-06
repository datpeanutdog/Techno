<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ListingController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AccountController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/login', [AccountController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/forgot-password', [AccountController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AccountController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{slug}', [ListingController::class, 'show']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])
        ->middleware('throttle:30,1');
    Route::post('/listings/{listing:slug}/messages', [ConversationController::class, 'start'])
        ->middleware('throttle:20,1');
    Route::get('/auth/me', [AccountController::class, 'me']);
    Route::post('/auth/logout', [AccountController::class, 'logout']);
    Route::put('/auth/profile', [AccountController::class, 'updateProfile']);
    Route::get('/my-listings', [ListingController::class, 'mine']);
    Route::get('/my-listings/{id}', [ListingController::class, 'owned']);
    Route::put('/my-listings/{id}', [ListingController::class, 'update']);
    Route::delete('/my-listings/{id}', [ListingController::class, 'destroy']);
    Route::post('/listings', [ListingController::class, 'store'])->middleware('throttle:10,1');
});
