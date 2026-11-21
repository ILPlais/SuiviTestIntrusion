<?php

namespace App\Http\Api;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

/**
 * Paramètres d'une liste de l'API : pagination, tri et filtres (11.03).
 *
 * Chaque contrôleur de liste déclare les champs triables et filtrables ; tout
 * autre champ est refusé (422 validation_failed), pour qu'aucun client ne
 * puisse trier ou filtrer sur une colonne interne.
 *
 * Paramètres lus dans l'URL : limit, cursor, page, sort, order, filter[champ].
 * Mode : page si ?page= est fourni, curseur si ?cursor= est fourni, sinon le
 * mode par défaut de l'instance (config api.pagination).
 *
 * Exemple :
 *
 *     return ApiListQuery::make($request, sortable: ['titre'], filterable: ['statut'])
 *         ->respond(Vulnerabilite::query(), fn ($v) => VulnerabiliteResource::make($v));
 */
final class ApiListQuery
{
	/**
	 * Paramètres déjà validés par make().
	 *
	 * @param 'page'|'cursor'        $mode    Mode de pagination retenu.
	 * @param array<string, string> $filters Filtres autorisés : colonne => valeur (égalité stricte).
	 */
	private function __construct(
		private readonly int $limit,
		private readonly string $mode,
		private readonly ?string $cursor,
		private readonly int $page,
		private readonly string $sort,
		private readonly string $order,
		private readonly array $filters,
	) {
	}

	/**
	 * Valide les paramètres de la requête et prépare la liste.
	 *
	 * @param list<string> $sortable    Colonnes autorisées pour le tri.
	 * @param list<string> $filterable  Colonnes autorisées pour les filtres.
	 * @param string       $defaultSort Tri par défaut, en ordre décroissant (le plus récent d'abord).
	 *
	 * @throws \Illuminate\Validation\ValidationException Si un paramètre est invalide ou non autorisé.
	 */
	public static function make(Request $request, array $sortable, array $filterable, string $defaultSort = 'updated_at'): self
	{
		$max = (int) config('api.limit.max', 200);

		$valid = Validator::make($request->query(), [
			'limit' => ['sometimes', 'integer', 'min:1', "max:{$max}"],
			'page' => ['sometimes', 'integer', 'min:1'],
			'cursor' => ['sometimes', 'string'],
			'sort' => ['sometimes', 'string', Rule::in($sortable)],
			'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
			// « array:a,b » refuse toute clé absente de la liste blanche.
			'filter' => ['sometimes', 'array:'.implode(',', $filterable)],
			'filter.*' => ['string', 'max:255'],
		])->validate();

		$mode = match (true) {
			$request->query->has('page') => 'page',
			$request->query->has('cursor') => 'cursor',
			default => config('api.pagination') === 'page' ? 'page' : 'cursor',
		};

		return new self(
			limit: (int) ($valid['limit'] ?? config('api.limit.default', 50)),
			mode: $mode,
			cursor: $valid['cursor'] ?? null,
			page: (int) ($valid['page'] ?? 1),
			sort: $valid['sort'] ?? $defaultSort,
			order: $valid['order'] ?? (isset($valid['sort']) ? 'asc' : 'desc'),
			filters: $valid['filter'] ?? [],
		);
	}

	/**
	 * Applique filtres, tri et pagination, puis renvoie la réponse {data, meta}.
	 *
	 * @template TModel of Model
	 *
	 * @param Builder<TModel>         $query     Requête de base, déjà limitée au tenant et aux droits.
	 * @param callable(TModel): mixed $transform Transforme chaque modèle pour la réponse.
	 */
	public function respond(Builder $query, callable $transform): JsonResponse
	{
		foreach ($this->filters as $field => $value) {
			$query->where($field, $value);
		}

		// La clé primaire en second tri rend l'ordre total : sans elle, deux lignes de
		// même valeur pourraient être répétées ou sautées d'une tranche à l'autre.
		$query->orderBy($this->sort, $this->order)
			->orderBy($query->getModel()->getKeyName(), $this->order);

		try {
			$paginator = $this->mode === 'page'
				? $query->paginate($this->limit, ['*'], 'page', $this->page)
				: $query->cursorPaginate($this->limit, ['*'], 'cursor', Cursor::fromEncoded($this->cursor));
		} catch (UnexpectedValueException) {
			// Curseur valide mais produit pour un autre tri : il ne contient pas les
			// colonnes attendues. C'est une erreur du client (422), pas du serveur.
			throw ValidationException::withMessages(['cursor' => [__('validation.custom.cursor.invalid')]]);
		}

		return new JsonResponse([
			'data' => array_map($transform, $paginator->items()),
			'meta' => $this->meta($paginator),
		]);
	}

	/**
	 * Indications de navigation : curseurs, ou page et total en mode page.
	 *
	 * Le total n'est pas calculé en mode curseur : compter toutes les lignes à
	 * chaque requête serait coûteux sur une grosse base (11.03).
	 *
	 * @param CursorPaginator<int, Model>|LengthAwarePaginator<int, Model> $paginator
	 *
	 * @return array<string, int|string|null>
	 */
	private function meta(CursorPaginator|LengthAwarePaginator $paginator): array
	{
		$meta = ['limit' => $this->limit, 'count' => count($paginator->items())];

		if ($paginator instanceof LengthAwarePaginator) {
			return $meta + [
				'page' => $paginator->currentPage(),
				'last_page' => $paginator->lastPage(),
				'total' => $paginator->total(),
			];
		}

		return $meta + [
			'next_cursor' => $paginator->nextCursor()?->encode(),
			'prev_cursor' => $paginator->previousCursor()?->encode(),
		];
	}
}
