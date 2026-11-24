<?php

/**
 * Configuration de l'application : routes, middlewares et rendu des exceptions.
 */

use App\Http\Api\ApiErrorRenderer;
use App\Http\Api\ConcurrencyLimit;
use App\Http\Api\LimitJsonSize;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
	->withRouting(
		web: __DIR__.'/../routes/web.php',
		api: __DIR__.'/../routes/api.php',
		commands: __DIR__.'/../routes/console.php',
		health: '/up',
	)
	->withMiddleware(function (Middleware $middleware): void {
		// Corps JSON de l'API limités en taille ; requêtes simultanées par classe (socle-5).
		// Sessions Sanctum pour la SPA servie sur la même origine (identite-1, 13.06).
		$middleware->statefulApi();
		// En-têtes de sécurité sur toutes les réponses (socle-6, 13.04, 13.06), en tête de
		// pile : les réponses des autres middlewares (maintenance, 413) les reçoivent aussi.
		$middleware->prepend(SecurityHeaders::class);
		// Les navigateurs envoient les rapports CSP sans cookie ni jeton CSRF.
		$middleware->preventRequestForgery(except: ['api/v1/csp-reports']);
		$middleware->api(append: [LimitJsonSize::class]);
		$middleware->alias(['concurrent' => ConcurrencyLimit::class, 'two-factor' => EnsureTwoFactorEnrolled::class, 'active' => EnsureAccountActive::class]);

		// Un appel non authentifié à l'API reçoit une erreur 401, pas une redirection
		// vers une page de connexion (la SPA gère elle-même l'écran de connexion).
		$middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
	})
	->withExceptions(function (Exceptions $exceptions): void {
		$exceptions->shouldRenderJsonWhen(
			fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
		);

		// Toute erreur de l'API prend la forme {code, message, details} (11.03).
		$exceptions->render(
			fn (Throwable $e, Request $request) => $request->is('api/*') ? ApiErrorRenderer::render($e) : null,
		);
	})->create();
