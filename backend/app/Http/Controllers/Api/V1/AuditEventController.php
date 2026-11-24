<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Consultation du journal d'audit (identite-6, 11.02), réservée aux
 * administrateurs d'instance (Gate view-audit-log).
 *
 * Lecture seule : aucune route n'écrit ni ne supprime d'événement. La liste
 * se lit du plus récent au plus ancien, de préférence par curseur (11.03 :
 * des événements arrivent pendant le parcours sans décaler les tranches), et se
 * filtre par action, auteur, organisation ou objet.
 */
class AuditEventController extends Controller
{
	/**
	 * Liste paginée et filtrable des événements.
	 *
	 * @param Request $request Requête de liste (filtres, tri, pagination).
	 * @return JsonResponse
	 */
	public function index(Request $request): JsonResponse
	{
		Gate::authorize('view-audit-log');

		return ApiListQuery::make(
			$request,
			sortable: ['created_at'],
			filterable: ['action', 'user_id', 'tenant_id', 'subject_type', 'subject_id'],
			defaultSort: 'created_at',
		)->respond(AuditEvent::query()->with('author:id,name'), fn (AuditEvent $event) => $this->present($event));
	}

	/**
	 * Représentation d'un événement dans l'API.
	 *
	 * @param AuditEvent $event Événement à présenter.
	 * @return array<string, mixed>
	 */
	private function present(AuditEvent $event): array
	{
		$author = $event->author;

		return [
			'id' => $event->id,
			'action' => $event->action->value,
			'author' => $author !== null ? ['id' => $author->id, 'name' => $author->name] : null,
			'tenant_id' => $event->tenant_id,
			'subject' => $event->subject_type !== null ? ['type' => $event->subject_type, 'id' => $event->subject_id] : null,
			'before' => $event->before,
			'after' => $event->after,
			'ip_address' => $event->ip_address,
			'created_at' => $event->created_at->toIso8601ZuluString(),
		];
	}
}
