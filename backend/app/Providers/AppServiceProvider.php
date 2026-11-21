<?php

namespace App\Providers;

use App\Http\Api\RateLimits;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		// Classes de limites de débit de l'API : throttle:standard, heavy, export, auth.
		RateLimits::register();

		// Un joker dans TRUSTED_PROXIES est écarté (13.06) ; l'exploitant en est averti,
		// car son proxy n'est alors pas reconnu (URL en HTTP, adresse du proxy journalisée).
		if (config('trustedproxy.wildcard_rejected')) {
			Log::warning('TRUSTED_PROXIES contient une valeur qui fait confiance à tout client (« * », REMOTE_ADDR, réseau /0), refusée : indiquez l\'adresse du proxy inverse.');
		}
	}
}
