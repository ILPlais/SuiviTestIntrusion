<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| API REST versionnée : toutes les routes sont sous /api/v1/ (11.03).
*/
Route::prefix('v1')->name('v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    Route::get('user', fn (Request $request) => $request->user())
        ->middleware('auth:sanctum')
        ->name('user');
});
