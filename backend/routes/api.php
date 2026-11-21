<?php

use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InstanceController;
use Illuminate\Support\Facades\Route;

/*
| API REST versionnée : toutes les routes sont sous /api/v1/ (11.03), avec la
| classe de débit Standard ; la connexion et l'activation suivent la classe Auth.
*/
Route::prefix('v1')->name('v1.')->middleware('throttle:standard')->group(function () {
	Route::get('health', HealthController::class)->name('health');
	Route::get('instance', InstanceController::class)->name('instance');

	// Authentification des comptes locaux (identite-1, 13.03) : publique, limitée par adresse IP.
	Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
		Route::post('login', [AuthController::class, 'login'])->name('login');
		Route::get('activation/{token}', [ActivationController::class, 'show'])->name('activation.show');
		Route::post('activate', [ActivationController::class, 'store'])->name('activate');
	});

	Route::middleware('auth:sanctum')->group(function () {
		Route::get('user', [AuthController::class, 'me'])->name('user');
		Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
	});
});
