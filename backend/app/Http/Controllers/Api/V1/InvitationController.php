<?php

namespace App\Http\Controllers\Api\V1;

use App\Accounts\AccountLifecycle;
use App\Audit\AuditLog;
use App\Auth\AuthenticationValue;
use App\Enums\AuditAction;
use App\Enums\TenantRole;
use App\Http\Api\ApiListQuery;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Invitations (identite-7, 11.06, écran 16 de 15.01).
 *
 * Il n'y a pas d'inscription libre : tout compte naît d'une invitation. Un
 * administrateur d'instance invite pour tout rôle ; un chef de projet invite
 * des clients finaux dans les organisations où il est chef de projet. Le lien
 * n'est envoyé par aucun e-mail en V1 (13.06) : la réponse le donne à
 * l'invitant, qui le transmet par un autre canal. Le serveur n'en garde que
 * l'empreinte ; il ne peut donc pas le réafficher, seulement en émettre un
 * nouveau (renvoi), qui invalide le précédent.
 */
class InvitationController extends Controller
{
	/**
	 * Reçoit le cycle de vie des comptes et le journal d'audit.
	 *
	 * @param AccountLifecycle $accounts Liens, révocations et invitations.
	 * @param AuditLog $audit Journal d'audit (identite-6).
	 */
	public function __construct(private readonly AccountLifecycle $accounts, private readonly AuditLog $audit)
	{
	}

	/**
	 * Invitations en attente : toutes pour un administrateur, les siennes pour un chef de projet.
	 *
	 * @param Request $request Requête de liste (tri, pagination).
	 * @return JsonResponse
	 */
	public function index(Request $request): JsonResponse
	{
		$user = $this->actor($request);
		abort_unless($user->canInvite(), 403);

		$query = Invitation::query()->pending()->with(['user', 'inviter', 'tenant']);
		if (! $user->is_instance_admin) {
			$query->where('invited_by', $user->id);
		}

		return ApiListQuery::make($request, sortable: ['created_at', 'expires_at'], filterable: [], defaultSort: 'created_at')
			->respond($query, fn (Invitation $invitation) => $this->present($invitation));
	}

	/**
	 * Invite un compte : crée le compte en attente et son lien, ou rattache
	 * aussitôt un compte actif à l'organisation (sans nouvel enrôlement).
	 *
	 * @param Request $request Requête portant l'adresse, l'organisation et le rôle.
	 * @return JsonResponse
	 *
	 * @throws ValidationException
	 */
	public function store(Request $request): JsonResponse
	{
		$inviter = $this->actor($request);
		abort_unless($inviter->canInvite(), 403);

		$data = $request->validate([
			'email' => ['required', 'string', 'email', 'max:255'],
			'name' => ['nullable', 'string', 'max:255'],
			'tenant_id' => ['nullable', 'string', 'required_with:role'],
			'role' => ['nullable', 'string', 'required_with:tenant_id', Rule::enum(TenantRole::class)],
			'is_instance_admin' => ['sometimes', 'boolean'],
		]);

		$role = isset($data['role']) ? TenantRole::from($data['role']) : null;
		$asAdmin = (bool) ($data['is_instance_admin'] ?? false);
		$tenant = isset($data['tenant_id']) ? Tenant::query()->withoutGlobalScope(TenantScope::class)->whereKey($data['tenant_id'])->first() : null;

		if (isset($data['tenant_id']) && $tenant === null) {
			throw ValidationException::withMessages(['tenant_id' => [__('validation.exists', ['attribute' => 'tenant_id'])]]);
		}
		if ($tenant === null && ! $asAdmin) {
			throw ValidationException::withMessages(['tenant_id' => [__('accounts.target_required')]]);
		}
		// Chef de projet : clients finaux de ses propres organisations uniquement
		// (sans le rôle d'administrateur, l'organisation est forcément fournie ici).
		if (! $inviter->is_instance_admin && ($asAdmin || $role !== TenantRole::Client || $inviter->roleIn($tenant) !== TenantRole::ProjectManager)) {
			throw ValidationException::withMessages(['role' => [__('accounts.invite_forbidden_role')]]);
		}

		$email = AuthenticationValue::normalizeEmail($data['email']);

		return DB::transaction(function () use ($inviter, $data, $email, $tenant, $role, $asAdmin): JsonResponse {
			$user = User::query()->where('email', $email)->lockForUpdate()->first();

			if ($user !== null && $user->deactivated_at !== null) {
				throw ValidationException::withMessages(['email' => [__('accounts.account_disabled')]]);
			}
			if ($user !== null && $tenant !== null && $user->roleIn($tenant) !== null) {
				throw ValidationException::withMessages(['email' => [__('accounts.already_member')]]);
			}
			if ($user !== null && $tenant === null && $user->is_instance_admin) {
				throw ValidationException::withMessages(['email' => [__('accounts.already_admin')]]);
			}
			if ($user !== null && ! $user->canSignIn()) {
				$this->assertLinkControl($inviter, $user, 'email');
			}

			$user ??= (new User())->forceFill([
				'name' => (string) (($data['name'] ?? null) ?: Str::before($email, '@')),
				'email' => $email,
				'password' => null,
			]);
			$user->save();

			$invitation = (new Invitation())->forceFill([
				'user_id' => $user->id,
				'invited_by' => $inviter->id,
				'tenant_id' => $tenant?->id,
				'role' => $role,
				'is_instance_admin' => $asAdmin,
			]);
			$invitation->save();

			$link = null;
			if ($user->canSignIn()) {
				// Compte actif : rattaché sans nouvel enrôlement ; il le verra dans ses organisations.
				$this->accounts->apply($user, $invitation);
			} else {
				// Compte en attente (nouveau, ou déjà invité) : un nouveau lien remplace le précédent.
				$link = $this->accounts->issueActivationLink($user);
				$invitation->refresh();
			}

			$this->audit->record(AuditAction::AccountInvited, $user, after: [
				'email' => $email,
				'role' => $role?->value,
				'is_instance_admin' => $asAdmin,
				'attached' => $link === null,
			], tenant: $tenant?->id);

			return new JsonResponse(['data' => $this->present($invitation->load(['user', 'inviter', 'tenant'])), 'link' => $link['link'] ?? null], 201);
		});
	}

	/**
	 * Renvoie une invitation : nouveau lien, l'ancien cesse de valoir.
	 *
	 * @param Request $request Requête courante.
	 * @param Invitation $invitation Invitation en attente.
	 * @return JsonResponse
	 *
	 * @throws ValidationException
	 */
	public function resend(Request $request, Invitation $invitation): JsonResponse
	{
		$this->authorizeManage($request, $invitation);
		$user = $invitation->user;

		if ($user->getAttributes()['password'] !== null) {
			throw ValidationException::withMessages(['invitation' => [__('accounts.account_already_active')]]);
		}
		$this->assertLinkControl($this->actor($request), $user, 'invitation');

		$link = $this->accounts->issueActivationLink($user);
		$this->audit->record(AuditAction::AccountInvited, $user, after: ['email' => $user->email, 'resent' => true], tenant: $invitation->tenant_id);

		return new JsonResponse(['data' => $this->present($invitation->refresh()->load(['user', 'inviter', 'tenant'])), 'link' => $link['link']]);
	}

	/**
	 * Annule une invitation. Si le compte invité n'a plus d'invitation en
	 * attente et n'a jamais été activé, son lien est aussitôt invalidé.
	 *
	 * @param Request $request Requête courante.
	 * @param Invitation $invitation Invitation en attente.
	 * @return Response
	 */
	public function destroy(Request $request, Invitation $invitation): Response
	{
		$this->authorizeManage($request, $invitation);
		self::cancel($invitation);
		$this->audit->record(AuditAction::InvitationCancelled, $invitation->user, after: ['email' => $invitation->user->email], tenant: $invitation->tenant_id);

		return response()->noContent();
	}

	/**
	 * Marque l'invitation annulée et retire le lien d'un compte en attente qui
	 * n'a plus d'autre invitation. Partagé avec la désactivation de l'invitant.
	 *
	 * @param Invitation $invitation Invitation en attente.
	 * @return void
	 */
	public static function cancel(Invitation $invitation): void
	{
		$invitation->forceFill(['cancelled_at' => now()])->save();
		$user = $invitation->user;

		if ($user->getAttributes()['password'] === null && ! $user->invitations()->pending()->exists()) {
			$user->forceFill(['activation_token_hash' => null, 'activation_expires_at' => null])->save();
		}
	}

	/**
	 * Un nouveau lien remplace celui du compte et applique, à l'activation, toutes
	 * ses invitations en attente. Un chef de projet ne peut donc émettre ce lien
	 * que si toutes ces invitations sont les siennes : sans quoi, en activant
	 * lui-même le compte, il recevrait le rôle promis par un administrateur.
	 *
	 * @param User $inviter Auteur de la demande.
	 * @param User $user Compte en attente d'activation.
	 * @param string $field Champ auquel rattacher le refus.
	 * @return void
	 *
	 * @throws ValidationException
	 */
	private function assertLinkControl(User $inviter, User $user, string $field): void
	{
		if (! $inviter->is_instance_admin && $user->invitations()->pending()->where(fn ($query) => $query->whereNull('invited_by')->orWhere('invited_by', '!=', $inviter->id))->exists()) {
			throw ValidationException::withMessages([$field => [__('accounts.invited_by_other')]]);
		}
	}

	/**
	 * Refuse en 403 (404 hors de portée) la gestion d'une invitation : seuls
	 * l'invitant et un administrateur la renvoient ou l'annulent.
	 *
	 * @param Request $request Requête courante.
	 * @param Invitation $invitation Invitation visée.
	 * @return void
	 *
	 * @throws ValidationException
	 */
	private function authorizeManage(Request $request, Invitation $invitation): void
	{
		$user = $this->actor($request);

		// Une invitation d'un autre invitant est introuvable pour un chef de projet.
		abort_unless($user->is_instance_admin || $invitation->invited_by === $user->id, 404);

		if (! $invitation->isPending()) {
			throw ValidationException::withMessages(['invitation' => [__('accounts.invitation_closed')]]);
		}
	}

	/**
	 * Représentation d'une invitation dans l'API.
	 *
	 * @param Invitation $invitation Invitation, avec compte, invitant et organisation chargés.
	 * @return array<string, mixed>
	 */
	private function present(Invitation $invitation): array
	{
		return [
			'id' => $invitation->id,
			'email' => $invitation->user->email,
			'name' => $invitation->user->name,
			'tenant' => $invitation->tenant === null ? null : ['id' => $invitation->tenant->id, 'name' => $invitation->tenant->name],
			'role' => $invitation->role?->value,
			'is_instance_admin' => $invitation->is_instance_admin,
			'status' => $invitation->accepted_at !== null ? 'accepted' : ($invitation->expires_at !== null && $invitation->expires_at->isPast() ? 'expired' : 'pending'),
			'expires_at' => $invitation->expires_at?->toIso8601String(),
			'invited_by' => $invitation->inviter === null ? null : ['id' => $invitation->inviter->id, 'name' => $invitation->inviter->name],
			'created_at' => $invitation->created_at?->toIso8601String(),
		];
	}

	/**
	 * Utilisateur connecté.
	 *
	 * @param Request $request Requête courante.
	 * @return User
	 */
	private function actor(Request $request): User
	{
		$user = $request->user();
		abort_unless($user instanceof User, 401);

		return $user;
	}
}
