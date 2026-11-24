<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Tenancy\TenantScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $job_title
 * @property string|null $presentation
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
 * @property string|null $public_key
 * @property string|null $key_fingerprint
 * @property string|null $private_key_password_copy
 * @property string|null $private_key_recovery_copy
 * @property int $key_version
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $deactivated_at
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $pseudonymized_at
 * @property string|null $reset_token_hash
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $reset_expires_at
 */
#[Fillable(['name', 'email', 'password', 'job_title', 'presentation'])]
#[Hidden(['password', 'remember_token', 'activation_token_hash', 'reset_token_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'public_key', 'private_key_password_copy', 'private_key_recovery_copy'])]
class User extends Authenticatable
{
	use HasApiTokens;
	/** @use HasFactory<UserFactory> */
	use HasFactory;
	use Notifiable;

	/**
	 * Valeurs par défaut, identiques à celles de la base : un compte tout juste
	 * créé, pas encore relu, n'est jamais administrateur.
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes = ['is_instance_admin' => false];

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
	 * Invitations reçues par ce compte (identite-7).
	 *
	 * @return HasMany<Invitation, $this>
	 */
	public function invitations(): HasMany
	{
		return $this->hasMany(Invitation::class);
	}

	/**
	 * Vrai si le compte peut se connecter : activé, ni désactivé ni supprimé (identite-7).
	 *
	 * @return bool
	 */
	public function canSignIn(): bool
	{
		return $this->getAttributes()['password'] !== null && $this->deactivated_at === null && $this->pseudonymized_at === null;
	}

	/**
	 * État du compte affiché à l'administration (écran 16 de 15.01) : supprimé,
	 * désactivé, invité (jamais activé), verrouillé ou actif, dans cet ordre de priorité.
	 *
	 * @return 'deleted'|'deactivated'|'invited'|'locked'|'active'
	 */
	public function status(): string
	{
		return match (true) {
			$this->pseudonymized_at !== null => 'deleted',
			$this->deactivated_at !== null => 'deactivated',
			$this->getAttributes()['password'] === null => 'invited',
			$this->locked_until !== null && $this->locked_until->isFuture() => 'locked',
			default => 'active',
		};
	}

	/**
	 * Vrai si le compte peut inviter : administrateur d'instance, ou chef de projet
	 * d'au moins une organisation, qui invite alors ses clients finaux (11.06).
	 *
	 * @return bool
	 */
	public function canInvite(): bool
	{
		return $this->is_instance_admin || in_array(TenantRole::ProjectManager, $this->tenantRoles(), true);
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
	 * @return array{id: int, name: string, email: string, is_instance_admin: bool, job_title: string|null, presentation: string|null, two_factor_enabled: bool, two_factor_setup_required: bool, key_fingerprint: string|null, can_invite: bool}
	 */
	public function toPublicArray(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
			'is_instance_admin' => $this->is_instance_admin,
			'job_title' => $this->job_title,
			'presentation' => $this->presentation,
			'two_factor_enabled' => $this->hasTwoFactor(),
			'two_factor_setup_required' => (bool) config('suivi.authentication.two_factor_required') && ! $this->hasTwoFactor(),
			// Empreinte de la paire de clés (ged-chiffree-1) ; nulle pour un compte enrôlé avant le coffre.
			'key_fingerprint' => $this->key_fingerprint,
			// Bouton « Inviter » de la SPA (identite-7) ; le serveur contrôle chaque invitation.
			'can_invite' => $this->canInvite(),
		];
	}

	/**
	 * Vrai si le compte a un coffre de clés (ged-chiffree-1). Les comptes activés
	 * avant le coffre n'en ont pas ; ils en recevront un à leur prochain enrôlement.
	 *
	 * @return bool
	 */
	public function hasVault(): bool
	{
		return $this->key_fingerprint !== null;
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
			'key_version' => 'integer',
			'deactivated_at' => 'datetime',
			'pseudonymized_at' => 'datetime',
			'reset_expires_at' => 'datetime',
		];
	}
}
