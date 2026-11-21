<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Point de réception des rapports de violation CSP (socle-6, 13.04).
 *
 * Public, car les navigateurs envoient les rapports sans cookie ; limité en débit
 * (classe Standard) et en taille. Accepte l'ancien format `report-uri`
 * (application/csp-report) et le nouveau `report-to` (application/reports+json).
 * Chaque violation est écrite dans le journal technique, sauf le refus attendu de
 * la politique Trusted Types « vue », qui survient à chaque chargement.
 */
class CspReportController extends Controller
{
	/**
	 * Reçoit un rapport, journalise ses violations et répond 204.
	 *
	 * @param Request $request Requête du navigateur (rapport JSON).
	 * @return Response Réponse vide (204), ou 413 si le rapport est trop gros.
	 */
	public function __invoke(Request $request): Response
	{
		$body = $request->getContent();
		abort_if(strlen($body) > (int) config('security.report_max_bytes'), 413);

		foreach ($this->violations(json_decode($body, true)) as $violation) {
			if (! $this->isExpected($violation)) {
				Log::warning('Violation de la politique de sécurité du contenu (CSP).', $violation);
			}
		}

		return response()->noContent();
	}

	/**
	 * Extrait les violations des deux formats de rapport, en ne gardant que des
	 * champs courts et utiles au diagnostic.
	 *
	 * @param mixed $payload Rapport décodé (tableau attendu).
	 * @return list<array<string, string>>
	 */
	private function violations(mixed $payload): array
	{
		if (! is_array($payload)) {
			return [];
		}

		// Format report-uri : {"csp-report": {...}} ; format report-to : [{"type": …, "body": {...}}].
		$bodies = isset($payload['csp-report']) ? [$payload['csp-report']] : array_column(array_filter($payload, 'is_array'), 'body');

		$violations = [];
		foreach ($bodies as $body) {
			if (! is_array($body) || $body === []) {
				continue;
			}
			$violations[] = array_map(
				fn ($value) => mb_substr(is_scalar($value) ? (string) $value : '', 0, 300),
				array_intersect_key($body, array_flip([
					'document-uri', 'documentURL', 'violated-directive', 'effective-directive', 'effectiveDirective',
					'blocked-uri', 'blockedURL', 'source-file', 'sourceFile', 'line-number', 'lineNumber', 'script-sample', 'sample',
				])),
			);
		}

		return $violations;
	}

	/**
	 * Vrai pour le refus attendu de la politique Trusted Types « vue » (13.04).
	 *
	 * @param array<string, string> $violation Violation extraite d'un rapport.
	 * @return bool Vrai si la violation est attendue et ne doit pas être journalisée.
	 */
	private function isExpected(array $violation): bool
	{
		$directive = $violation['violated-directive'] ?? $violation['effectiveDirective'] ?? $violation['effective-directive'] ?? '';
		$sample = $violation['script-sample'] ?? $violation['sample'] ?? '';

		return str_starts_with($directive, 'trusted-types') && trim($sample) === 'vue';
	}
}
