<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;

Route::get('/plans', [SubscriptionController::class, 'getPlans']);
Route::post('/webhook', [SubscriptionController::class, 'handleWebhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-subscription', [SubscriptionController::class, 'getMySubscription']);
    Route::post('/wompi-checkout', [SubscriptionController::class, 'wompiCheckout']);
});
