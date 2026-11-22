<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Fiche de l'entreprise prestataire (engagement-1, 11.05) : lue par tout
 * utilisateur connecté, remplacée par un administrateur d'instance.
 */
class CompanyProfileController extends Controller
{
	/**
	 * La fiche de l'entreprise ; champs nuls tant qu'elle n'est pas remplie.
	 *
	 * @return JsonResponse
	 */
	public function show(): JsonResponse
	{
		return new JsonResponse(['data' => $this->present(CompanyProfile::current())]);
	}

	/**
	 * Remplace la fiche (un champ absent est vidé).
	 *
	 * @param Request $request Requête portant les champs de la fiche.
	 * @return JsonResponse
	 */
	public function update(Request $request): JsonResponse
	{
		Gate::authorize('manage-company-profile');

		$valid = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'legal_name' => ['nullable', 'string', 'max:255'],
			'presentation' => ['nullable', 'string', 'max:20000'],
		]);

		$profile = CompanyProfile::current();
		$profile->fill(['name' => $valid['name'], 'legal_name' => $valid['legal_name'] ?? null, 'presentation' => $valid['presentation'] ?? null]);
		$profile->save();

		return new JsonResponse(['data' => $this->present($profile)]);
	}

	/**
	 * Représentation de la fiche dans l'API.
	 *
	 * @param CompanyProfile $profile Fiche à présenter.
	 * @return array{name: string|null, legal_name: string|null, presentation: string|null}
	 */
	private function present(CompanyProfile $profile): array
	{
		return [
			'name' => $profile->name,
			'legal_name' => $profile->legal_name,
			'presentation' => $profile->presentation,
		];
	}
}
