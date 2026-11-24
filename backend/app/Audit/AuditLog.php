<?php

namespace App\Audit;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Point d'entrée unique du journal d'audit (identite-6, 11.02).
 *
 * Chaque action sensible appelle record() : l'auteur (utilisateur connecté par
 * défaut), l'horodatage, l'organisation et l'objet visés, l'action et le résumé
 * avant / après. Le résumé passe par redact() : toute clé qui ressemble à un
 * secret (mot de passe, valeur d'authentification, jeton, clé privée, CEK,
 * codes de secours…) est remplacée par un marqueur, à toute profondeur. Les
 * journaux ne contiennent jamais de secret en clair, même si un appelant se
 * trompe.
 */
final class AuditLog
{
	/** Marqueur qui remplace la valeur d'un champ secret. */
	public const REDACTED = '[masqué]';

	/**
	 * Motif des noms de champs secrets. « auth » seul est la valeur
	 * d'authentification de 13.03 ; les suffixes `_hash` couvrent les jetons
	 * hachés (activation) ; `wrapped`, `private` et `cek` les clés de la GED
	 * chiffrée (ged-chiffree-*).
	 */
	private const SECRET_KEY = '/(^auth$|^auth_|password|passwd|secret|token|recovery|private|wrapped|^cek|_cek|_hash$|^code$|_code$)/i';

	/**
	 * Longueur maximale d'un texte dans un résumé : une description Markdown
	 * entière alourdirait le journal sans aider à savoir qui a fait quoi.
	 */
	public const MAX_TEXT = 500;

	/** Colonnes de service sans intérêt pour l'audit, retirées des résumés. */
	private const IGNORED = ['created_at', 'updated_at'];

	/**
	 * Reçoit la requête courante (adresse IP), absente en ligne de commande.
	 *
	 * @param Request|null $request Requête HTTP en cours.
	 */
	public function __construct(private readonly ?Request $request = null)
	{
	}

	/**
	 * Consigne une action.
	 *
	 * @param AuditAction $action Action réalisée.
	 * @param Model|null $subject Objet visé ; son organisation est reprise si `tenant` n'est pas donné.
	 * @param array<string, mixed>|null $before État avant (champs pertinents seulement).
	 * @param array<string, mixed>|null $after État après, ou contexte de l'action.
	 * @param User|null $actor Auteur ; par défaut l'utilisateur connecté.
	 * @param string|null $tenant Organisation concernée, si l'objet ne la porte pas.
	 * @return AuditEvent
	 */
	public function record(AuditAction $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?User $actor = null, ?string $tenant = null): AuditEvent
	{
		$actor ??= Auth::user() instanceof User ? Auth::user() : null;

		$event = new AuditEvent([
			'tenant_id' => $tenant ?? $this->tenantOf($subject),
			'user_id' => $actor?->id,
			'action' => $action,
			'subject_type' => $subject !== null ? self::subjectType($subject) : null,
			'subject_id' => $subject !== null ? (string) $subject->getKey() : null,
			'before' => $before !== null ? self::redact($before) : null,
			'after' => $after !== null ? self::redact($after) : null,
			'ip_address' => $this->request?->ip(),
		]);
		$event->save();

		return $event;
	}

	/**
	 * Consigne la modification d'un modèle juste enregistré : seuls les champs
	 * réellement changés figurent, avec leur ancienne et leur nouvelle valeur.
	 * Rien n'est écrit si aucun champ n'a changé.
	 *
	 * À appeler après save() / update(), tant que getChanges() reflète l'écriture.
	 *
	 * @param AuditAction $action Action réalisée.
	 * @param Model $subject Modèle enregistré.
	 * @return AuditEvent|null
	 */
	public function recordChanges(AuditAction $action, Model $subject): ?AuditEvent
	{
		$after = array_diff_key($subject->getChanges(), array_flip(self::IGNORED));
		if ($after === []) {
			return null;
		}

		// getPrevious() garde les valeurs d'avant le dernier save() ; getOriginal()
		// est déjà resynchronisé sur les nouvelles.
		$before = array_intersect_key($subject->getPrevious(), $after);

		return $this->record($action, $subject, self::normalize($before), self::normalize($after));
	}

	/**
	 * Instantané d'un modèle (création, suppression), sans colonnes de service.
	 *
	 * @param Model $subject Modèle à résumer.
	 * @param list<string> $only Champs à garder ; tous si vide.
	 * @return array<string, mixed>
	 */
	public static function snapshot(Model $subject, array $only = []): array
	{
		$attributes = array_diff_key($subject->attributesToArray(), array_flip(self::IGNORED));

		return $only === [] ? $attributes : array_intersect_key($attributes, array_flip($only));
	}

	/**
	 * Remplace, à toute profondeur, la valeur des champs secrets par un marqueur
	 * et tronque les textes trop longs (MAX_TEXT).
	 *
	 * @param array<array-key, mixed> $data Résumé à nettoyer.
	 * @return array<array-key, mixed>
	 */
	public static function redact(array $data): array
	{
		$clean = [];
		foreach ($data as $key => $value) {
			if (is_string($key) && preg_match(self::SECRET_KEY, $key) === 1) {
				// Seule la présence d'une valeur est consignée, jamais la valeur.
				$clean[$key] = $value === null ? null : self::REDACTED;
			} elseif (is_array($value)) {
				$clean[$key] = self::redact($value);
			} elseif (is_string($value) && mb_strlen($value) > self::MAX_TEXT) {
				$clean[$key] = mb_substr($value, 0, self::MAX_TEXT).'…';
			} else {
				$clean[$key] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Type court et stable de l'objet visé, indépendant du nom de classe PHP
	 * (« PenetrationTest » → « penetration_test »).
	 *
	 * @param Model $subject Objet visé.
	 * @return string
	 */
	public static function subjectType(Model $subject): string
	{
		return $subject->getTable() === 'tests' ? 'test' : str($subject->getTable())->singular()->toString();
	}

	/**
	 * Organisation portée par l'objet : lui-même s'il en est une, sinon sa colonne `tenant_id`.
	 *
	 * @param Model|null $subject Objet visé.
	 * @return string|null
	 */
	private function tenantOf(?Model $subject): ?string
	{
		if ($subject instanceof Tenant) {
			return $subject->id;
		}

		$tenant = $subject?->getAttribute('tenant_id');

		return is_string($tenant) ? $tenant : null;
	}

	/**
	 * Convertit les valeurs brutes (énumérations, dates) en scalaires JSON.
	 *
	 * @param array<string, mixed> $values Valeurs à convertir.
	 * @return array<string, mixed>
	 */
	private static function normalize(array $values): array
	{
		return array_map(static fn (mixed $value): mixed => match (true) {
			$value instanceof BackedEnum => $value->value,
			$value instanceof UnitEnum => $value->name,
			$value instanceof DateTimeInterface => $value->format(DATE_ATOM),
			default => $value,
		}, $values);
	}
}
