<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RemediationPriority;
use App\Enums\RemediationStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\VulnerabilityPresenter;
use App\Models\Remediation;
use App\Models\User;
use App\Models\Vulnerability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fiche de remédiation d'une vulnérabilité (vulnerabilites-5, 11.09) : une
 * seule route de modification, PATCH /vulnerabilities/{id}/remediation, qui
 * renvoie la fiche de vulnérabilité complète.
 *
 * Droits (VulnerabilityPolicy) : l'équipe du test rédige et suit le statut ; le
 * chef de projet ou l'administrateur surcharge la priorité ; le client final
 * peut seulement déclarer la correction (statut « Corrigé »).
 */
class RemediationController extends Controller
{
	/**
	 * Modifie les champs fournis de la fiche de remédiation.
	 *
	 * @param Request $request Requête portant les champs modifiés.
	 * @param Vulnerability $vulnerability Vulnérabilité résolue à travers le filtre (404 sinon).
	 * @return JsonResponse
	 */
	public function update(Request $request, Vulnerability $vulnerability): JsonResponse
	{
		$valid = $request->validate([
			'recommendations' => ['sometimes', 'nullable', 'string', 'max:20000'],
			'resources' => ['sometimes', 'array', 'max:50'],
			// Adresses web seulement : un lien javascript: ou data: serait cliquable dans la fiche.
			'resources.*' => ['string', 'distinct', 'max:2048', 'url:http,https'],
			'priority' => ['sometimes', 'nullable', Rule::enum(RemediationPriority::class)],
			'status' => ['sometimes', Rule::enum(RemediationStatus::class)],
			'retest_result' => ['nullable', 'string', 'max:2000', 'required_if:status,verified'],
		]);

		$this->authorizeChanges($valid, $vulnerability);

		/** @var User $user */
		$user = $request->user();

		DB::transaction(function () use ($vulnerability, $valid, $user): void {
			/** @var Remediation $remediation */
			$remediation = $vulnerability->remediation()->firstOr(fn () => Remediation::forVulnerability($vulnerability));
			$remediation->fill(array_intersect_key($valid, array_flip(['recommendations', 'resources'])));
			if (array_key_exists('priority', $valid)) {
				$remediation->prioritize(RemediationPriority::tryFrom((string) $valid['priority']), $vulnerability->severity);
			}
			if (array_key_exists('status', $valid)) {
				$remediation->changeStatus(RemediationStatus::from($valid['status']), $valid['retest_result'] ?? null, $user);
			}
			$remediation->save();
		});

		return VulnerabilityPresenter::response($vulnerability, $user);
	}

	/**
	 * Vérifie les droits selon les champs envoyés : déclarer « Corrigé » seul
	 * suffit au client, mais seulement depuis « À faire » ou « En cours » (il ne
	 * peut pas annuler un retest) ; tout autre changement exige l'équipe, et la
	 * priorité le chef de projet.
	 *
	 * @param array<string, mixed> $valid Champs validés.
	 * @param Vulnerability $vulnerability Vulnérabilité visée.
	 * @return void
	 *
	 * @throws ValidationException Si le client déclare une correction déjà déclarée ou vérifiée.
	 */
	private function authorizeChanges(array $valid, Vulnerability $vulnerability): void
	{
		$onlyDeclaresFix = array_keys($valid) === ['status'] && $valid['status'] === RemediationStatus::Fixed->value;
		if ($onlyDeclaresFix && Gate::denies('remediate', $vulnerability)) {
			Gate::authorize('declareFixed', $vulnerability);
			// Statut converti en énumération par le modèle (et non la chaîne de la base).
			$current = $vulnerability->remediation()->first()?->status;
			if (! in_array($current, [RemediationStatus::Todo, RemediationStatus::InProgress], true)) {
				throw ValidationException::withMessages(['status' => [__('validation.custom.status.already_fixed')]]);
			}

			return;
		}

		Gate::authorize('remediate', $vulnerability);
		if (array_key_exists('priority', $valid)) {
			Gate::authorize('prioritize', $vulnerability);
		}
	}
}
