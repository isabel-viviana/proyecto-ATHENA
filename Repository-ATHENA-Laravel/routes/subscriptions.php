<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;

Route::get('/plans', [SubscriptionController::class, 'getPlans']);
Route::get('/my-subscription', [SubscriptionController::class, 'getMySubscription']);
Route::post('/wompi-checkout', [SubscriptionController::class, 'wompiCheckout']);
Route::post('/webhook', [SubscriptionController::class, 'handleWebhook']);
