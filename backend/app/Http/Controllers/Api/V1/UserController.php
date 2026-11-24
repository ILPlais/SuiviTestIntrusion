<?php

namespace App\Http\Controllers\Api\V1;

use App\Accounts\AccountLifecycle;
use App\Audit\AuditLog;
use App\Auth\AccountLockout;
use App\Enums\AuditAction;
use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Administration des comptes de l'instance (identite-7, 11.06, écran 16 de 15.01).
 *
 * Réservée aux administrateurs d'instance (Gate manage-users). Désactivation
 * réversible, suppression définitive d'un compte déjà désactivé (pseudonymisé),
 * déverrouillage anticipé (13.03) et lien de réinitialisation du mot de passe.
 * Le dernier administrateur actif n'est jamais désactivé ni supprimé.
 */
class UserController extends Controller
{
	/**
	 * Reçoit le cycle de vie des comptes, le verrouillage et le journal d'audit.
	 *
	 * @param AccountLifecycle $accounts Liens et révocations.
	 * @param AccountLockout $lockout Verrouillage des comptes (13.03).
	 * @param AuditLog $audit Journal d'audit (identite-6).
	 */
	public function __construct(private readonly AccountLifecycle $accounts, private readonly AccountLockout $lockout, private readonly AuditLog $audit)
	{
	}

	/**
	 * Comptes de l'instance, hors comptes supprimés, avec leur état.
	 *
	 * @param Request $request Requête de liste (tri, pagination, filtre).
	 * @return JsonResponse
	 */
	public function index(Request $request): JsonResponse
	{
		Gate::authorize('manage-users');

		return ApiListQuery::make($request, sortable: ['name', 'email', 'created_at'], filterable: [], defaultSort: 'name')
			->respond(User::query()->whereNull('pseudonymized_at')->with('tenants'), fn (User $user) => $this->present($user));
	}

	/**
	 * Désactive un compte : connexion refusée, sessions et jetons révoqués,
	 * invitations qu'il a émises annulées (11.06).
	 *
	 * @param Request $request Requête courante.
	 * @param User $user Compte visé.
	 * @return JsonResponse
	 *
	 * @throws ValidationException
	 */
	public function deactivate(Request $request, User $user): JsonResponse
	{
		Gate::authorize('manage-users');
		$this->assertNotSelf($request, $user);
		abort_if($user->pseudonymized_at !== null, 404);

		DB::transaction(function () use ($user): void {
			$locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
			// Dernier administrateur actif : sans lui, plus personne n'administrerait l'instance.
			if ($locked->is_instance_admin && $locked->canSignIn() && $this->accounts->activeAdminCount() <= 1) {
				throw ValidationException::withMessages(['user' => [__('accounts.last_admin')]]);
			}
			$user->forceFill(['deactivated_at' => now()])->save();

			foreach (Invitation::query()->pending()->where('invited_by', $user->id)->get() as $invitation) {
				InvitationController::cancel($invitation);
			}
		});

		$this->accounts->revokeAccess($user);
		$this->audit->record(AuditAction::AccountDeactivated, $user, after: ['deactivated_at' => $user->deactivated_at?->toIso8601String()]);

		return new JsonResponse(['data' => $this->present($user->refresh())]);
	}

	/**
	 * Réactive un compte désactivé : la connexion est rétablie.
	 *
	 * @param User $user Compte désactivé.
	 * @return JsonResponse
	 *
	 * @throws ValidationException
	 */
	public function reactivate(User $user): JsonResponse
	{
		Gate::authorize('manage-users');
		abort_if($user->pseudonymized_at !== null, 404);

		if ($user->deactivated_at === null) {
			throw ValidationException::withMessages(['user' => [__('accounts.not_deactivated_state')]]);
		}

		$user->forceFill(['deactivated_at' => null])->save();
		$this->audit->record(AuditAction::AccountReactivated, $user);

		return new JsonResponse(['data' => $this->present($user->refresh())]);
	}

	/**
	 * Supprime un compte désactivé : données d'authentification, second facteur,
	 * clé publique et copies de la clé privée effacées, rattachements retirés,
	 * adresse libérée ; la ligne reste, pseudonymisée, pour l'historique (11.06).
	 * L'empreinte de la clé reste dans le journal d'audit (13.05).
	 *
	 * @param Request $request Requête courante.
	 * @param User $user Compte désactivé.
	 * @return Response
	 *
	 * @throws ValidationException
	 */
	public function destroy(Request $request, User $user): Response
	{
		Gate::authorize('manage-users');
		$this->assertNotSelf($request, $user);
		abort_if($user->pseudonymized_at !== null, 404);

		if ($user->deactivated_at === null) {
			throw ValidationException::withMessages(['user' => [__('accounts.not_deactivated')]]);
		}

		$before = ['email' => $user->email, 'name' => $user->name, 'key_fingerprint' => $user->key_fingerprint];

		DB::transaction(function () use ($user): void {
			$user->tenants()->detach();
			$user->invitations()->delete();
			$user->tokens()->delete();
			$user->forceFill([
				'name' => __('accounts.deleted_name', ['id' => $user->id]),
				// Adresse libérée : une nouvelle invitation créera un nouveau compte.
				// Le domaine réservé « .invalid » (RFC 2606) ne reçoit jamais de courrier.
				'email' => "deleted-{$user->id}@deleted.invalid",
				'password' => null,
				'remember_token' => null,
				'job_title' => null,
				'presentation' => null,
				'is_instance_admin' => false,
				'activation_token_hash' => null,
				'activation_expires_at' => null,
				'reset_token_hash' => null,
				'reset_expires_at' => null,
				'two_factor_secret' => null,
				'two_factor_confirmed_at' => null,
				'two_factor_recovery_codes' => null,
				'two_factor_last_timestep' => null,
				'public_key' => null,
				'key_fingerprint' => null,
				'private_key_password_copy' => null,
				'private_key_recovery_copy' => null,
				'key_version' => 0,
				'failed_login_count' => 0,
				'last_failed_login_at' => null,
				'locked_until' => null,
				'pseudonymized_at' => now(),
			])->save();
		});

		$this->accounts->revokeAccess($user);
		$this->audit->record(AuditAction::AccountDeleted, $user, before: $before);

		return response()->noContent();
	}

	/**
	 * Déverrouille un compte avant l'échéance du verrouillage (13.03).
	 *
	 * @param User $user Compte verrouillé.
	 * @return JsonResponse
	 */
	public function unlock(User $user): JsonResponse
	{
		Gate::authorize('manage-users');
		abort_if($user->pseudonymized_at !== null, 404);

		$this->lockout->reset($user);
		$this->audit->record(AuditAction::AccountUnlocked, $user);

		return new JsonResponse(['data' => $this->present($user->refresh())]);
	}

	/**
	 * Émet un lien de réinitialisation du mot de passe, valable 24 heures, à
	 * transmettre par l'administrateur (aucun e-mail en V1). Le lien ne livre que
	 * la copie de la clé privée chiffrée par la clé de récupération (13.03).
	 *
	 * @param User $user Compte actif, avec un coffre de clés.
	 * @return JsonResponse
	 *
	 * @throws ValidationException
	 */
	public function issuePasswordReset(User $user): JsonResponse
	{
		Gate::authorize('manage-users');
		abort_if($user->pseudonymized_at !== null, 404);

		if (! $user->canSignIn()) {
			throw ValidationException::withMessages(['user' => [__('accounts.not_active')]]);
		}
		if (! $user->hasVault()) {
			throw ValidationException::withMessages(['user' => [__('accounts.no_vault')]]);
		}

		$link = $this->accounts->issueResetLink($user);
		$this->audit->record(AuditAction::PasswordResetIssued, $user, after: ['reset_expires_at' => $link['expires_at']->toIso8601String()]);

		return new JsonResponse(['link' => $link['link'], 'expires_at' => $link['expires_at']->toIso8601String()]);
	}

	/**
	 * Refuse qu'un administrateur se désactive ou se supprime lui-même.
	 *
	 * @param Request $request Requête courante.
	 * @param User $user Compte visé.
	 * @return void
	 *
	 * @throws ValidationException
	 */
	private function assertNotSelf(Request $request, User $user): void
	{
		if ($request->user()?->getAuthIdentifier() === $user->id) {
			throw ValidationException::withMessages(['user' => [__('accounts.self')]]);
		}
	}

	/**
	 * Représentation d'un compte pour l'administration.
	 *
	 * @param User $user Compte, organisations chargées.
	 * @return array<string, mixed>
	 */
	private function present(User $user): array
	{
		return [
			'id' => $user->id,
			'name' => $user->name,
			'email' => $user->email,
			'status' => $user->status(),
			'is_instance_admin' => $user->is_instance_admin,
			'two_factor_enabled' => $user->hasTwoFactor(),
			'has_vault' => $user->hasVault(),
			'locked_until' => $user->locked_until?->isFuture() ? $user->locked_until->toIso8601String() : null,
			'deactivated_at' => $user->deactivated_at?->toIso8601String(),
			'activation_expires_at' => $user->getAttributes()['password'] === null ? $user->activation_expires_at?->toIso8601String() : null,
			'tenants' => $user->tenants->map(fn ($tenant) => [
				'id' => $tenant->id,
				'name' => $tenant->name,
				'role' => $tenant->getRelation('pivot')->getAttribute('role'),
			])->values()->all(),
		];
	}
}
