<?php

namespace App\Http\Api;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Convertit toute exception levée sous /api/ en réponse {code, message, details} (11.03).
 *
 * - Seuls les statuts de la table du CDC sont renvoyés ; les autres sont ramenés
 *   au plus proche (405 devient 404, 419 devient 401, un autre 4xx devient 422).
 * - Le message vient de lang/<langue>/errors.php ; le texte de l'exception n'est
 *   jamais renvoyé, même avec APP_DEBUG=true : il peut contenir une requête SQL,
 *   un chemin ou un secret. L'exception reste journalisée par Laravel.
 * - `details` ne porte que les erreurs de validation, champ par champ.
 */
final class ApiErrorRenderer
{
	/** Code stable de 11.03 pour chaque statut HTTP renvoyé. */
	private const CODES = [
		401 => 'unauthenticated',
		403 => 'forbidden',
		404 => 'not_found',
		409 => 'conflict',
		413 => 'payload_too_large',
		422 => 'validation_failed',
		429 => 'rate_limited',
		500 => 'internal_error',
		503 => 'internal_error',
	];

	/** En-têtes d'une réponse 429 transmis au client (11.03). */
	private const RATE_LIMIT_HEADERS = ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'];

	/** Statuts hors table ramenés à un statut de la table. */
	private const ALIASES = [
		400 => 422, // Requête mal formée : refusée comme une donnée invalide.
		405 => 404, // Méthode non prévue : la route n'existe pas pour cette méthode.
		419 => 401, // Jeton CSRF expiré (session Sanctum) : il faut se reconnecter.
	];

	/**
	 * Construit la réponse JSON d'erreur pour une exception.
	 *
	 * @param Throwable $e Exception déjà préparée par Laravel (ModelNotFoundException
	 *                     devenue NotFoundHttpException, AuthorizationException
	 *                     devenue AccessDeniedHttpException, etc.).
	 */
	public static function render(Throwable $e): JsonResponse
	{
		$status = self::status($e);
		$code = self::CODES[$status];
		$details = $e instanceof ValidationException ? $e->errors() : [];

		// Seuls Retry-After et les en-têtes X-RateLimit-* sont utiles au client (11.03) ;
		// les autres en-têtes de l'exception sont écartés.
		$headers = [];
		if ($status === 429 && $e instanceof HttpExceptionInterface) {
			$headers = array_map(
				fn ($value) => (string) $value,
				array_intersect_key($e->getHeaders(), array_flip(self::RATE_LIMIT_HEADERS)),
			);
		}

		return new JsonResponse([
			'code' => $code,
			'message' => __("errors.{$code}"),
			// Objet JSON {} même vide, pour que le client lise toujours le même type.
			'details' => (object) $details,
		], $status, $headers);
	}

	/**
	 * Détermine le statut HTTP de la table de 11.03 pour une exception.
	 */
	private static function status(Throwable $e): int
	{
		$status = match (true) {
			$e instanceof ValidationException => 422,
			$e instanceof AuthenticationException => 401,
			$e instanceof HttpExceptionInterface => $e->getStatusCode(),
			default => 500,
		};

		$status = self::ALIASES[$status] ?? $status;
		if (isset(self::CODES[$status])) {
			return $status;
		}

		return $status >= 500 ? 500 : 422;
	}
}
