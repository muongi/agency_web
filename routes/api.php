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
    Route::middleware('log.transaction.api')->group(function () {
        Route::post('/transactions/cash-deposit', [\App\Http\Controllers\Api\ApiTransactionController::class, 'cashDeposit']);
        Route::post('/transactions/biometric-withdraw', [\App\Http\Controllers\Api\ApiTransactionController::class, 'biometricWithdraw']);
        Route::post('/transactions/balance-enquiry', [\App\Http\Controllers\Api\ApiTransactionController::class, 'balanceEnquiry']);
        Route::post('/transactions/mini-statement', [\App\Http\Controllers\Api\ApiTransactionController::class, 'miniStatement']);
        Route::post('/transactions', [\App\Http\Controllers\Api\ApiTransactionController::class, 'store']);
    });

    Route::post('/agent/login', [\App\Http\Controllers\Api\AgentAuthController::class, 'login']);

    Route::post('/client/account/open', [\App\Http\Controllers\Api\ClientAccountController::class, 'openAccount']);

    Route::get('/members/{memberNo}/balances', [\App\Http\Controllers\Api\KatoMemberController::class, 'balances']);
    Route::get('/members/{memberNo}/mini-statement', [\App\Http\Controllers\Api\KatoMemberController::class, 'miniStatement']);
    Route::get('/accounts/{accountNumber}/balances', [\App\Http\Controllers\Api\KatoMemberController::class, 'balancesByAccount']);
    Route::get('/accounts/{accountNumber}/mini-statement', [\App\Http\Controllers\Api\KatoMemberController::class, 'miniStatementByAccount']);

    Route::middleware('log.otp.api')->group(function () {
        Route::post('/otp/send', [\App\Http\Controllers\Api\OtpController::class, 'sendOtp']);
        Route::post('/otp/forward', [\App\Http\Controllers\Api\OtpController::class, 'forwardOtp']);
    });

    Route::prefix('cnss')->group(function () {
        Route::post('/voluntary-enrollments', [\App\Http\Controllers\Api\CnssController::class, 'storeEnrollment']);
        Route::get('/voluntary-enrollments/{id}', [\App\Http\Controllers\Api\CnssController::class, 'showEnrollment']);
        Route::get('/members', [\App\Http\Controllers\Api\CnssController::class, 'searchMembers'])
            ->middleware('cnss.member.search');
        Route::post('/contributions', [\App\Http\Controllers\Api\CnssController::class, 'storeContribution']);
    });

    // Card management routes - public, requires business_unit_id in request
    Route::middleware('log.card.api')->group(function () {
        Route::get('/cards/uid/{uid}', [\App\Http\Controllers\CardController::class, 'getByUid']);
        Route::post('/cards/{id}/mark-written', [\App\Http\Controllers\CardController::class, 'markAsWritten']);
    });
});
