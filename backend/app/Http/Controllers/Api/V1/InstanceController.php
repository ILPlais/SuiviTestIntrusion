<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Identifiant public de l'instance (13.03), lu par la SPA pour calculer le sel
 * de la valeur d'authentification sans révéler l'existence d'un compte.
 */
class InstanceController extends Controller
{
	public function __invoke(): JsonResponse
	{
		return new JsonResponse(['instance_id' => DB::table('instance_settings')->value('instance_id')]);
	}
}
