<?php

namespace App\Providers;

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
		// Un joker dans TRUSTED_PROXIES est écarté (13.06) ; l'exploitant en est averti,
		// car son proxy n'est alors pas reconnu (URL en HTTP, adresse du proxy journalisée).
		if (config('trustedproxy.wildcard_rejected')) {
			Log::warning('TRUSTED_PROXIES contient « * », qui est refusé : indiquez l\'adresse du proxy inverse.');
		}
	}
}
