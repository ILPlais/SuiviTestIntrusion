<?php

namespace App\Audit;

use LogicException;

/**
 * Rend un événement d'audit inaltérable par Eloquent (identite-6) : une fois
 * écrit, il ne se modifie ni ne se supprime un à un. La purge de rétention
 * passe par MassPrunable, une suppression groupée qui ne déclenche pas ces
 * événements de modèle.
 */
trait ImmutableAuditEvent
{
	/**
	 * Refuse toute mise à jour et toute suppression unitaire.
	 *
	 * @return void
	 */
	public static function bootImmutableAuditEvent(): void
	{
		static::updating(static function (): never {
			throw new LogicException('Un événement d\'audit ne se modifie pas.');
		});

		static::deleting(static function (): never {
			throw new LogicException('Un événement d\'audit ne se supprime que par la purge de rétention.');
		});
	}
}
