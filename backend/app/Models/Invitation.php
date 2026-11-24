<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation d'un compte dans une organisation (identite-7, 11.06).
 *
 * Elle est « en attente » tant qu'elle n'est ni acceptée (compte activé) ni
 * annulée. Le lien à usage unique n'est pas ici : il appartient au compte
 * invité (`users.activation_token_hash`), qui n'en a qu'un à la fois.
 *
 * @property string $id
 * @property int $user_id
 * @property int|null $invited_by
 * @property string|null $tenant_id
 * @property TenantRole|null $role
 * @property bool $is_instance_admin
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $expires_at
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $accepted_at
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $cancelled_at
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $created_at
 */
class Invitation extends Model
{
	use HasUlids;

	/**
	 * Aucun attribut n'est rempli en masse : le contrôleur fixe chaque champ.
	 *
	 * @var list<string>
	 */
	protected $guarded = ['*'];

	/**
	 * Compte invité.
	 *
	 * @return BelongsTo<User, $this>
	 */
	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	/**
	 * Auteur de l'invitation.
	 *
	 * @return BelongsTo<User, $this>
	 */
	public function inviter(): BelongsTo
	{
		return $this->belongsTo(User::class, 'invited_by');
	}

	/**
	 * Organisation de l'invitation, lue sans le filtre de l'utilisateur courant :
	 * c'est un fait de rattachement, comme User::tenants().
	 *
	 * @return BelongsTo<Tenant, $this>
	 */
	public function tenant(): BelongsTo
	{
		return $this->belongsTo(Tenant::class)->withoutGlobalScope(TenantScope::class);
	}

	/**
	 * Invitations ni acceptées ni annulées.
	 *
	 * @param Builder<Invitation> $query Requête à restreindre.
	 * @return void
	 */
	public function scopePending(Builder $query): void
	{
		$query->whereNull('accepted_at')->whereNull('cancelled_at');
	}

	/**
	 * Vrai si l'invitation n'est ni acceptée ni annulée.
	 *
	 * @return bool
	 */
	public function isPending(): bool
	{
		return $this->accepted_at === null && $this->cancelled_at === null;
	}

	/**
	 * Conversions des attributs (rôle, dates).
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'role' => TenantRole::class,
			'is_instance_admin' => 'boolean',
			'expires_at' => 'datetime',
			'accepted_at' => 'datetime',
			'cancelled_at' => 'datetime',
		];
	}
}
