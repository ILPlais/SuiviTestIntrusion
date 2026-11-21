<?php

/**
 * Proxys de confiance (13.06, Sécurité HTTP).
 *
 * Le middleware TrustProxies de Laravel lit « proxies » : seuls ces pairs peuvent
 * imposer X-Forwarded-For, -Proto, -Host et -Port. En V1, c'est le proxy inverse de
 * l'exploitant qui termine le TLS ; sans valeur, aucun proxy n'est cru.
 */

use App\Http\TrustedProxyList;

return [

	// Adresses ou réseaux CIDR séparés par des virgules ; « * », REMOTE_ADDR et les réseaux /0 sont toujours refusés.
	'proxies' => TrustedProxyList::fromString(env('TRUSTED_PROXIES')),

	// Vrai si TRUSTED_PROXIES contenait une valeur écartée (signalé dans le journal).
	'wildcard_rejected' => TrustedProxyList::containsWildcard(env('TRUSTED_PROXIES')),

];
