<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $activation_expires_at
 */
#[Fillable(['name', 'email', 'password', 'job_title', 'presentation'])]
#[Hidden(['password', 'remember_token', 'activation_token_hash'])]
class User extends Authenticatable
{
	use HasApiTokens;
	/** @use HasFactory<UserFactory> */
	use HasFactory;
	use Notifiable;

	/**
	 * Champs exposés au compte lui-même par l'API (GET /api/v1/user).
	 *
	 * @return array{id: int, name: string, email: string, is_instance_admin: bool}
	 */
	public function toPublicArray(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
			'is_instance_admin' => $this->is_instance_admin,
		];
	}

	/**
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
		];
	}
}
