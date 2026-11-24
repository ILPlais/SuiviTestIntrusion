<?php

namespace App\Models;

use App\Audit\ImmutableAuditEvent;
use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * Événement du journal d'audit (identite-6, 11.02) : qui a fait quoi, quand,
 * sur quel objet, avec le résumé avant / après.
 *
 * Écrit uniquement par App\Audit\AuditLog, qui retire les secrets ; une ligne
 * n'est jamais modifiée ni supprimée une à une (ImmutableAuditEvent). Seule la
 * purge de rétention (`php artisan model:prune`, planifiée chaque jour) efface
 * les événements trop anciens, par requête groupée.
 *
 * Pas de filtre d'organisation (BelongsToTenant) : un événement d'instance n'a
 * pas d'organisation, et la lecture est réservée aux administrateurs d'instance
 * (Gate view-audit-log).
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon $created_at
 */
#[Fillable(['tenant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip_address'])]
class AuditEvent extends Model
{
	use HasUlids;
	use ImmutableAuditEvent;
	use MassPrunable;

	/**
	 * Pas de date de mise à jour : un événement ne change pas.
	 *
	 * @var string|null
	 */
	public const UPDATED_AT = null;

	/**
	 * Auteur de l'action ; null pour une tentative anonyme ou un compte purgé.
	 *
	 * @return BelongsTo<User, $this>
	 */
	public function author(): BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	/**
	 * Événements à purger : plus anciens que la rétention. Une rétention nulle
	 * ou négative conserve tout (aucune ligne sélectionnée).
	 *
	 * @return Builder<static>
	 */
	public function prunable(): Builder
	{
		$days = (int) config('suivi.audit.retention_days');

		if ($days <= 0) {
			return static::query()->whereRaw('1 = 0');
		}

		return static::query()->where('created_at', '<', Date::now()->subDays($days));
	}

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'action' => AuditAction::class,
			'before' => 'array',
			'after' => 'array',
		];
	}
}
