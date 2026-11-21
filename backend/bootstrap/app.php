<?php

/**
 * Configuration de l'application : routes, middlewares et rendu des exceptions.
 */

use App\Http\Api\ApiErrorRenderer;
use App\Http\Api\ConcurrencyLimit;
use App\Http\Api\LimitJsonSize;
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
		$middleware->api(append: [LimitJsonSize::class]);
		$middleware->alias(['concurrent' => ConcurrencyLimit::class]);

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
