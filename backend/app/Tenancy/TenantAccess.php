<?php

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Organisations accessibles à l'utilisateur courant (identite-5, 14.01).
 *
 * Le filtre échoue fermé : sans utilisateur authentifié, aucune organisation n'est
 * accessible. Les traitements sans utilisateur (commandes, données de
 * démonstration) lèvent explicitement la restriction avec withoutRestriction().
 */
final class TenantAccess
{
	/**
	 * Vrai pendant un appel à withoutRestriction().
	 *
	 * @var bool
	 */
	private static bool $unrestricted = false;

	/**
	 * Identifiants des organisations accessibles, ou null si aucune restriction ne
	 * s'applique (administrateur d'instance, ou restriction levée).
	 *
	 * @return list<string>|null
	 */
	public static function visibleTenantIds(): ?array
	{
		if (self::$unrestricted) {
			return null;
		}

		$user = Auth::user();
		if (! $user instanceof User) {
			return [];
		}

		if ($user->is_instance_admin) {
			return null;
		}

		// Lecture directe de la table de liaison : passer par le modèle Tenant
		// appliquerait son propre filtre, qui dépend de cette méthode.
		/** @var list<string> $ids */
		$ids = DB::table('tenant_user')->where('user_id', $user->id)->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();

		return $ids;
	}

	/**
	 * Vrai si l'organisation est accessible à l'utilisateur courant.
	 *
	 * @param string|null $tenantId Identifiant de l'organisation.
	 * @return bool
	 */
	public static function allows(?string $tenantId): bool
	{
		$ids = self::visibleTenantIds();

		return $ids === null || ($tenantId !== null && in_array($tenantId, $ids, true));
	}

	/**
	 * Exécute un traitement sans filtre d'organisation (commande d'administration,
	 * données de démonstration, tests). La restriction est rétablie ensuite, même
	 * en cas d'exception.
	 *
	 * @template TResult
	 *
	 * @param callable(): TResult $callback Traitement à exécuter.
	 * @return TResult
	 */
	public static function withoutRestriction(callable $callback): mixed
	{
		$previous = self::$unrestricted;
		self::$unrestricted = true;

		try {
			return $callback();
		} finally {
			self::$unrestricted = $previous;
		}
	}
}
