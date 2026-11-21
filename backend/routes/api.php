<?php

use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InstanceController;
use App\Http\Controllers\Api\V1\TwoFactorController;
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
		Route::post('two-factor', [AuthController::class, 'twoFactorChallenge'])->name('two-factor');
		Route::get('activation/{token}', [ActivationController::class, 'show'])->name('activation.show');
		Route::post('activate', [ActivationController::class, 'store'])->name('activate');
	});

	// Profil, déconnexion et double authentification : accessibles même quand la
	// politique d'instance impose un second facteur pas encore configuré.
	Route::middleware('auth:sanctum')->group(function () {
		Route::get('user', [AuthController::class, 'me'])->name('user');
		Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

		Route::prefix('user/two-factor')->name('user.two-factor.')->group(function () {
			Route::post('', [TwoFactorController::class, 'store'])->name('store');
			Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
			Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
			Route::delete('', [TwoFactorController::class, 'destroy'])->name('destroy');
		});
	});

	// Les routes métier se placeront dans un groupe auth:sanctum + two-factor.
});
