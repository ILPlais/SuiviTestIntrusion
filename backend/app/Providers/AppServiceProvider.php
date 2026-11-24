<?php

namespace App\Providers;

use App\Http\Api\RateLimits;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/** Fournisseur de services de l'application : limiteurs, événements et réglages globaux. */
class AppServiceProvider extends ServiceProvider
{
	/**
	 * Enregistre les services de l'application.
	 *
	 * @return void
	 */
	public function register(): void
	{
		// La documentation de l'API est servie par ApiDocumentationController, réservée aux
		// administrateurs : pas de routes /docs/api publiques (socle-6).
		Scramble::ignoreDefaultRoutes();
	}

	/**
	 * Configure les services au démarrage : limiteurs, événements, contrôles de configuration.
	 *
	 * @return void
	 */
	public function boot(): void
	{
		// Classes de limites de débit de l'API : throttle:standard, heavy, export, auth.
		RateLimits::register();

		// Documentation de l'API : administrateurs d'instance seulement (11.03).
		Gate::define('view-api-documentation', fn (User $user) => $user->is_instance_admin);

		// Fiche de l'entreprise prestataire : administrateurs d'instance (engagement-1).
		Gate::define('manage-company-profile', fn (User $user) => $user->is_instance_admin);

		// Journal d'audit : administrateurs d'instance (identite-6, « qui a fait quoi et quand »).
		Gate::define('view-audit-log', fn (User $user) => $user->is_instance_admin);

		// Un joker dans TRUSTED_PROXIES est écarté (13.06) ; l'exploitant en est averti,
		// car son proxy n'est alors pas reconnu (URL en HTTP, adresse du proxy journalisée).
		if (config('trustedproxy.wildcard_rejected')) {
			Log::warning('TRUSTED_PROXIES contient une valeur qui fait confiance à tout client (« * », REMOTE_ADDR, réseau /0), refusée : indiquez l\'adresse du proxy inverse.');
		}
	}
}
