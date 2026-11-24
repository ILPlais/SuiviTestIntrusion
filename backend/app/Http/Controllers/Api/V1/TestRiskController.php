<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenetrationTest;
use App\Models\Vulnerability;
use App\Results\TestRisk;
use Illuminate\Http\JsonResponse;

/**
 * Risque global d'un test (resultats-1, 11.12) : GET /tests/{test}/risk, lisible
 * par tous les membres de l'organisation, comme la fiche du test ; un test
 * étranger est introuvable (404) par le filtre d'organisation.
 */
class TestRiskController extends Controller
{
	/**
	 * Calcule le risque global du test.
	 *
	 * @param PenetrationTest $test Test résolu à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function __invoke(PenetrationTest $test): JsonResponse
	{
		$vulnerabilities = Vulnerability::query()
			->where('test_id', $test->id)
			->with(['phase', 'remediation'])
			->get();

		return new JsonResponse(['data' => TestRisk::compute($vulnerabilities)]);
	}
}
