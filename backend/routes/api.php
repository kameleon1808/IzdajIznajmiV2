<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingRequestController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\LandlordListingController;
use App\Http\Controllers\ListingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::get('/listings', [ListingController::class, 'index'])->middleware('throttle:listings_search');
Route::get('/listings/{listing}', [ListingController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/landlord/listings', [LandlordListingController::class, 'index']);
    Route::post('/landlord/listings', [LandlordListingController::class, 'store'])->middleware('throttle:landlord_write');
    Route::put('/landlord/listings/{listing}', [LandlordListingController::class, 'update'])->middleware('throttle:landlord_write');
    Route::patch('/landlord/listings/{listing}/publish', [LandlordListingController::class, 'publish'])->middleware('throttle:landlord_write');
    Route::patch('/landlord/listings/{listing}/unpublish', [LandlordListingController::class, 'unpublish'])->middleware('throttle:landlord_write');
    Route::patch('/landlord/listings/{listing}/archive', [LandlordListingController::class, 'archive'])->middleware('throttle:landlord_write');
    Route::patch('/landlord/listings/{listing}/restore', [LandlordListingController::class, 'restore'])->middleware('throttle:landlord_write');

    Route::post('/booking-requests', [BookingRequestController::class, 'store'])->middleware('throttle:booking_requests');
    Route::get('/booking-requests', [BookingRequestController::class, 'index']);
    Route::patch('/booking-requests/{bookingRequest}', [BookingRequestController::class, 'updateStatus']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
});
