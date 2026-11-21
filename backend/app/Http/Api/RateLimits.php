<?php

namespace App\Http\Api;

use App\Events\RateLimitExceeded;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Classes de limites de débit de l'API (11.03, socle-5).
 *
 * Chaque classe est un limiteur nommé de Laravel, appliqué par le middleware
 * « throttle:<classe> » : standard (toute l'API v1), heavy (téléversements GED,
 * partage de CEK), export (PDF, OpenDocument, migration) et auth (connexion, 2FA).
 * Les plafonds viennent de config/api.php et sont relus à chaque requête.
 *
 * Le compteur est propre à l'utilisateur connecté, ou à l'adresse IP pour un appel
 * anonyme ; la classe auth compte toujours par adresse IP (bourrage d'identifiants
 * réparti sur plusieurs comptes) et allonge l'attente à chaque nouveau blocage.
 */
final class RateLimits
{
	/** Durée pendant laquelle les blocages de la classe auth sont mémorisés. */
	private const AUTH_LOCKOUT_MEMORY_SECONDS = 86400;

	/**
	 * Déclare les limiteurs nommés ; appelé au démarrage (AppServiceProvider).
	 */
	public static function register(): void
	{
		foreach (['standard', 'heavy', 'export'] as $class) {
			RateLimiter::for($class, fn (Request $request) => Limit::perMinute(self::perMinute($class))
				->by(self::requesterKey($request))
				->response(fn (Request $request, array $headers) => self::reject($request, $class, $headers)));
		}

		RateLimiter::for('auth', function (Request $request) {
			$ip = self::networkKey((string) $request->ip());

			return (new Limit('ip:'.$ip, self::perMinute('auth'), self::authDecaySeconds($ip)))
				->response(function (Request $request, array $headers) use ($ip) {
					self::recordAuthLockout($ip, (int) ($headers['Retry-After'] ?? 60));

					self::reject($request, 'auth', $headers);
				});
		});
	}

	/**
	 * Identifie le demandeur : l'utilisateur connecté, sinon son adresse IP.
	 */
	public static function requesterKey(Request $request): string
	{
		$user = $request->user();

		return $user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip();
	}

	/**
	 * Signale le dépassement (événement d'audit) et lève l'erreur 429 de l'API,
	 * avec les en-têtes X-RateLimit-* et Retry-After calculés par Laravel.
	 *
	 * @param array<string, mixed> $headers
	 */
	public static function reject(Request $request, string $class, array $headers): never
	{
		RateLimitExceeded::dispatch(
			$class,
			(string) $request->ip(),
			$request->user()?->getAuthIdentifier(),
			$request->method(),
			$request->path(),
		);

		throw new ThrottleRequestsException('Too Many Attempts.', null, $headers);
	}

	/**
	 * Plafond par minute d'une classe, lu dans config/api.php.
	 */
	private static function perMinute(string $class): int
	{
		return max(1, (int) config("api.rate_limits.{$class}.per_minute"));
	}

	/**
	 * Adresse servant de clé à la classe auth : l'adresse IPv4 telle quelle, ou le
	 * préfixe /64 d'une adresse IPv6, car un client dispose souvent d'un /64 entier
	 * et multiplierait sinon ses compteurs en changeant d'adresse.
	 */
	public static function networkKey(string $ip): string
	{
		$binary = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? inet_pton($ip) : false;
		if ($binary === false) {
			return $ip;
		}

		return inet_ntop(substr($binary, 0, 8).str_repeat("\0", 8)).'/64';
	}

	/**
	 * Fenêtre de la classe auth : 60 s, doublée à chaque blocage récent, sans
	 * dépasser max_lockout_seconds (« attente de plus en plus longue », 11.03).
	 *
	 * La fenêtre est fixée au premier essai d'une période : après un blocage, le
	 * client retrouve ses essais à la fin de l'attente, mais sur une fenêtre deux fois
	 * plus longue ; le débit possible est divisé par deux à chaque blocage.
	 */
	private static function authDecaySeconds(string $ip): int
	{
		$lockouts = min((int) Cache::get(self::lockoutCountKey($ip), 0), 16);
		$maximum = max(60, (int) config('api.rate_limits.auth.max_lockout_seconds'));

		return min(60 * (2 ** $lockouts), $maximum);
	}

	/**
	 * Compte un blocage de la classe auth, une seule fois par période de blocage :
	 * les requêtes refusées pendant l'attente ne l'allongent pas davantage.
	 */
	private static function recordAuthLockout(string $ip, int $retryAfter): void
	{
		if (Cache::add('api:auth-locked:'.$ip, true, max(1, $retryAfter))) {
			// Incrément atomique : deux blocages simultanés comptent bien pour deux.
			Cache::add(self::lockoutCountKey($ip), 0, self::AUTH_LOCKOUT_MEMORY_SECONDS);
			Cache::increment(self::lockoutCountKey($ip));
		}
	}

	/**
	 * Clé du nombre de blocages récents d'une adresse IP pour la classe auth.
	 */
	private static function lockoutCountKey(string $ip): string
	{
		return 'api:auth-lockouts:'.$ip;
	}
}
