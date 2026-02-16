<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API v1 routes
Route::prefix('v1')->group(function () {
    Route::post('/transactions', [\App\Http\Controllers\Api\ApiTransactionController::class, 'store']);
    
    Route::post('/agent/login', [\App\Http\Controllers\Api\AgentAuthController::class, 'login']);
    
    Route::post('/client/account/open', [\App\Http\Controllers\Api\ClientAccountController::class, 'openAccount']);
    
    Route::post('/otp/send', [\App\Http\Controllers\Api\OtpController::class, 'sendOtp']);
    
    Route::post('/otp/forward', [\App\Http\Controllers\Api\OtpController::class, 'forwardOtp']);
    
    // Card management routes - public, requires business_unit_id in request
    Route::get('/cards/uid/{uid}', [\App\Http\Controllers\CardController::class, 'getByUid']);
    Route::post('/cards/{id}/mark-written', [\App\Http\Controllers\CardController::class, 'markAsWritten']);
});
