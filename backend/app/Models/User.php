<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Tenancy\TenantScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Utilisateur de l'application (14.01, 13.03).
 *
 * `password` contient le hachage Argon2id de la valeur d'authentification
 * calculée par le navigateur, jamais d'un mot de passe ; il est nul tant que le
 * compte n'est pas activé. Les colonnes de verrouillage sont gérées par
 * App\Auth\AccountLockout. HasApiTokens prépare les jetons Sanctum (11.03).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_instance_admin
 * @property int $failed_login_count
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $last_failed_login_at
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $locked_until
 * @property string|null $activation_token_hash
 * @property string|null $two_factor_secret
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property list<string>|null $two_factor_recovery_codes
 * @property int|null $two_factor_last_timestep
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $activation_expires_at
 */
#[Fillable(['name', 'email', 'password', 'job_title', 'presentation'])]
#[Hidden(['password', 'remember_token', 'activation_token_hash', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
	use HasApiTokens;
	/** @use HasFactory<UserFactory> */
	use HasFactory;
	use Notifiable;

	/**
	 * Organisations de rattachement, avec le rôle dans chacune (identite-5). C'est
	 * un fait de rattachement : il se lit sans le filtre de l'utilisateur courant.
	 *
	 * @return BelongsToMany<Tenant, $this>
	 */
	public function tenants(): BelongsToMany
	{
		return $this->belongsToMany(Tenant::class)->withoutGlobalScope(TenantScope::class)->withPivot('role')->withTimestamps();
	}

	/**
	 * Rôle du compte dans chacune de ses organisations.
	 *
	 * @return array<string, TenantRole> Identifiant d'organisation => rôle.
	 */
	public function tenantRoles(): array
	{
		$roles = [];
		foreach ($this->tenants()->get() as $tenant) {
			/** @var Pivot $pivot */
			$pivot = $tenant->getRelation('pivot');
			$roles[$tenant->id] = TenantRole::from((string) $pivot->getAttribute('role'));
		}

		return $roles;
	}

	/**
	 * Rôle du compte dans une organisation, ou null s'il n'y est pas rattaché.
	 *
	 * @param Tenant|string $tenant Organisation, ou son identifiant.
	 * @return TenantRole|null
	 */
	public function roleIn(Tenant|string $tenant): ?TenantRole
	{
		return $this->tenantRoles()[$tenant instanceof Tenant ? $tenant->id : $tenant] ?? null;
	}

	/**
	 * Vrai si la double authentification est confirmée (identite-3).
	 *
	 * @return bool
	 */
	public function hasTwoFactor(): bool
	{
		return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
	}

	/**
	 * Champs exposés au compte lui-même par l'API (GET /api/v1/user).
	 * « two_factor_setup_required » : la politique d'instance impose un second
	 * facteur que le compte n'a pas encore.
	 *
	 * @return array{id: int, name: string, email: string, is_instance_admin: bool, two_factor_enabled: bool, two_factor_setup_required: bool}
	 */
	public function toPublicArray(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
			'is_instance_admin' => $this->is_instance_admin,
			'two_factor_enabled' => $this->hasTwoFactor(),
			'two_factor_setup_required' => (bool) config('suivi.authentication.two_factor_required') && ! $this->hasTwoFactor(),
		];
	}

	/**
	 * Conversions des attributs (secret TOTP chiffré, dates, valeur d'authentification hachée).
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'password' => 'hashed',
			'is_instance_admin' => 'boolean',
			'failed_login_count' => 'integer',
			'last_failed_login_at' => 'datetime',
			'locked_until' => 'datetime',
			'activation_expires_at' => 'datetime',
			'two_factor_secret' => 'encrypted',
			'two_factor_confirmed_at' => 'datetime',
			'two_factor_recovery_codes' => 'array',
			'two_factor_last_timestep' => 'integer',
		];
	}
}
