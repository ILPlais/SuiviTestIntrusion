<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil de l'utilisateur connecté (engagement-1, 11.05, références « Testeur
 * d'intrusion ») : nom, poste et présentation. L'adresse, qui entre dans la
 * valeur d'authentification (13.03), et le statut d'administrateur ne se
 * modifient pas ici.
 */
class ProfileController extends Controller
{
	/**
	 * Met à jour les champs fournis du profil ; les autres champs sont ignorés.
	 *
	 * @param Request $request Requête portant les champs du profil.
	 * @return JsonResponse
	 */
	public function update(Request $request): JsonResponse
	{
		$valid = $request->validate([
			'name' => ['sometimes', 'required', 'string', 'max:255'],
			'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
			'presentation' => ['sometimes', 'nullable', 'string', 'max:20000'],
		]);

		/** @var User $user */
		$user = $request->user();
		$user->fill($valid)->save();

		return new JsonResponse($user->toPublicArray());
	}
}
