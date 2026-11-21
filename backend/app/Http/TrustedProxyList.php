<?php

namespace App\Http;

/**
 * Lecture de la variable TRUSTED_PROXIES (13.06, Sécurité HTTP).
 *
 * La valeur liste les adresses ou réseaux (CIDR) des proxys inverses autorisés à
 * transmettre les en-têtes X-Forwarded-*, séparés par des virgules. Les jokers « * »
 * et « ** », qui feraient confiance à n'importe quel client, sont écartés : un client
 * pourrait sinon se faire passer pour une connexion HTTPS ou usurper son adresse IP.
 */
final class TrustedProxyList
{
	/** Valeurs de Laravel qui font confiance à tout appelant ; jamais acceptées. */
	private const WILDCARDS = ['*', '**'];

	/**
	 * Transforme la valeur de TRUSTED_PROXIES en liste d'adresses, sans les jokers.
	 *
	 * @return list<string>
	 */
	public static function fromString(?string $value): array
	{
		return array_values(array_filter(
			self::split($value),
			fn (string $entry) => ! in_array($entry, self::WILDCARDS, true),
		));
	}

	/**
	 * Indique si la valeur contenait un joker écarté, pour le signaler dans le journal.
	 */
	public static function containsWildcard(?string $value): bool
	{
		return array_intersect(self::split($value), self::WILDCARDS) !== [];
	}

	/**
	 * Découpe la valeur sur les virgules et retire les entrées vides.
	 *
	 * @return list<string>
	 */
	private static function split(?string $value): array
	{
		$entries = array_map(trim(...), explode(',', (string) $value));

		return array_values(array_filter($entries, fn (string $entry) => $entry !== ''));
	}
}
