<?php

use App\Http\Controllers\Api\ApiDocumentationController;
use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\CspReportController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InstanceController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PenetrationTestController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RemediationController;
use App\Http\Controllers\Api\V1\ScopeObjectController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TestRiskController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VaultController;
use App\Http\Controllers\Api\V1\VulnerabilityController;
use Illuminate\Support\Facades\Route;

/*
| API REST versionnée : toutes les routes sont sous /api/v1/ (11.03), avec la
| classe de débit Standard ; la connexion et l'activation suivent la classe Auth.
*/
Route::prefix('v1')->name('v1.')->middleware('throttle:standard')->group(function () {
	Route::get('health', HealthController::class)->name('health');
	Route::get('instance', InstanceController::class)->name('instance');
	// Rapports de violation CSP (13.04) : publics, limités en débit et en taille.
	Route::post('csp-reports', CspReportController::class)->name('csp-reports');

	// Authentification des comptes locaux (identite-1, 13.03) : publique, limitée par adresse IP.
	Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
		Route::post('login', [AuthController::class, 'login'])->name('login');
		Route::post('two-factor', [AuthController::class, 'twoFactorChallenge'])->name('two-factor');
		Route::get('activation/{token}', [ActivationController::class, 'show'])->name('activation.show');
		Route::post('activate', [ActivationController::class, 'store'])->name('activate');
		// Mot de passe oublié (identite-7) : lien émis par un administrateur, copie 2 après le second facteur.
		Route::get('password-reset/{token}', [PasswordResetController::class, 'show'])->name('password-reset.show');
		Route::post('password-reset/{token}/open', [PasswordResetController::class, 'open'])->name('password-reset.open');
		Route::post('password-reset', [PasswordResetController::class, 'store'])->name('password-reset.store');
	});

	// Profil, déconnexion et double authentification : accessibles même quand la
	// politique d'instance impose un second facteur pas encore configuré.
	// « active » ferme la session d'un compte désactivé ou supprimé (identite-7).
	Route::middleware(['auth:sanctum', 'active'])->group(function () {
		Route::get('user', [AuthController::class, 'me'])->name('user');
		Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

		Route::prefix('user/two-factor')->name('user.two-factor.')->group(function () {
			Route::post('', [TwoFactorController::class, 'store'])->name('store');
			Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
			Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
			Route::delete('', [TwoFactorController::class, 'destroy'])->name('destroy');
		});
	});

	// Routes métier : session, politique de double authentification, et filtre
	// par organisation porté par les modèles (identite-5).
	Route::middleware(['auth:sanctum', 'active', 'two-factor'])->group(function () {
		Route::apiResource('tenants', TenantController::class);
		// Tests d'intrusion (engagement-2) ; le statut change par sa route dédiée,
		// qui trace chaque transition.
		Route::apiResource('tests', PenetrationTestController::class);
		Route::post('tests/{test}/status', [PenetrationTestController::class, 'changeStatus'])->name('tests.status');
		// Périmètre réseau (engagement-4) : liste et création par le test, le reste par l'objet.
		Route::apiResource('tests.scope-objects', ScopeObjectController::class)->shallow();
		// Fiches de vulnérabilité (vulnerabilites-3) : même découpage que le périmètre.
		Route::apiResource('tests.vulnerabilities', VulnerabilityController::class)->shallow();
		// Fiche de remédiation (vulnerabilites-5), créée avec la vulnérabilité : modification seule.
		Route::patch('vulnerabilities/{vulnerability}/remediation', [RemediationController::class, 'update'])->name('vulnerabilities.remediation');
		// Risque global d'un test (resultats-1), calculé à la lecture.
		Route::get('tests/{test}/risk', TestRiskController::class)->name('tests.risk');
		Route::get('company-profile', [CompanyProfileController::class, 'show'])->name('company-profile.show');
		Route::put('company-profile', [CompanyProfileController::class, 'update'])->name('company-profile.update');
		Route::patch('user/profile', [ProfileController::class, 'update'])->name('user.profile');
		// Coffre de clés et changement de mot de passe (ged-chiffree-1) : après
		// l'authentification complète, second facteur compris (13.03).
		Route::get('user/vault', [VaultController::class, 'show'])->name('user.vault');
		Route::put('user/password', [VaultController::class, 'changePassword'])->middleware('throttle:auth')->name('user.password');
		// Journal d'audit (identite-6) : lecture seule, administrateurs d'instance.
		Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');
		// Invitations (identite-7) : administrateurs, et chefs de projet pour leurs clients finaux.
		Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
		Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
		Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
		Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
		// Administration des comptes (identite-7) : administrateurs d'instance.
		Route::get('users', [UserController::class, 'index'])->name('users.index');
		Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
		Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
		Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
		Route::post('users/{user}/password-reset', [UserController::class, 'issuePasswordReset'])->name('users.password-reset');
		Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
	});
});

/*
| Documentation de l'API (socle-6, 11.03) : fichier OpenAPI 3.1 et Swagger UI,
| réservés aux administrateurs d'instance (Gate view-api-documentation).
*/
Route::middleware(['auth:sanctum', 'active', 'two-factor', 'throttle:standard'])->group(function () {
	Route::get('openapi.json', [ApiDocumentationController::class, 'specification'])->name('api.openapi');
	Route::get('documentation', [ApiDocumentationController::class, 'ui'])->name('api.documentation');
});
