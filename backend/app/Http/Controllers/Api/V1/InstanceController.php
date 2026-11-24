<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Informations publiques de l'instance, lues par la SPA avant toute connexion :
 * identifiant (13.03), qui sert au sel de la valeur d'authentification sans
 * révéler l'existence d'un compte, et délai d'inactivité après lequel la clé
 * privée est effacée de la mémoire du navigateur (ged-chiffree-2, 12.01 §4.3).
 */
class InstanceController extends Controller
{
	/**
	 * Renvoie les informations publiques de l'instance (identifiant du sel de `auth`, délai de verrouillage du coffre).
	 *
	 * @return JsonResponse
	 */
	public function __invoke(): JsonResponse
	{
		return new JsonResponse([
			'instance_id' => DB::table('instance_settings')->value('instance_id'),
			// Au moins une minute : un délai nul effacerait la clé aussitôt déverrouillée.
			'vault_idle_minutes' => max(1, (int) config('suivi.vault.idle_minutes')),
		]);
	}
}
