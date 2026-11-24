<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditLog;
use App\Auth\AccountLockout;
use App\Auth\TwoFactor;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Gestion de la double authentification par l'utilisateur connecté (identite-3).
 *
 * Enrôlement en deux temps : un secret est créé (non confirmé), puis confirmé
 * par un premier code, ce qui active la double authentification et renvoie les
 * codes de secours, affichés une seule fois. Désactiver ou renouveler les codes
 * demande un code valide ; la politique d'instance peut interdire de désactiver.
 */
class TwoFactorController extends Controller
{
	/**
	 * Reçoit le service TOTP, le service de verrouillage des comptes et le journal d'audit.
	 *
	 * @param TwoFactor $twoFactor Service TOTP.
	 * @param AccountLockout $lockout Verrouillage des comptes.
	 * @param AuditLog $audit Journal d'audit (identite-6).
	 */
	public function __construct(private readonly TwoFactor $twoFactor, private readonly AccountLockout $lockout, private readonly AuditLog $audit)
	{
	}

	/**
	 * Démarre (ou recommence) l'enrôlement : nouveau secret et URL du QR code.
	 *
	 * @param Request $request Requête courante.
	 * @return JsonResponse
	 */
	public function store(Request $request): JsonResponse
	{
		$user = $this->user($request);
		abort_if($user->hasTwoFactor(), 409);

		$secret = $this->twoFactor->generateSecret();
		$user->forceFill([
			'two_factor_secret' => $secret,
			'two_factor_confirmed_at' => null,
			'two_factor_recovery_codes' => null,
			'two_factor_last_timestep' => null,
		])->save();

		return new JsonResponse(['secret' => $secret, 'otpauth_url' => $this->twoFactor->otpauthUrl($user, $secret)]);
	}

	/**
	 * Confirme l'enrôlement par un premier code et renvoie les codes de secours.
	 *
	 * @param Request $request Requête portant le code de confirmation.
	 * @return JsonResponse
	 */
	public function confirm(Request $request): JsonResponse
	{
		$user = $this->user($request);
		$code = $this->validatedCode($request);
		abort_if($user->two_factor_secret === null || $user->hasTwoFactor(), 409);

		$this->requireValidCode($user, $code);
		$user->forceFill(['two_factor_confirmed_at' => now()])->save();
		Log::notice('Double authentification activée.', ['utilisateur' => $user->id]);
		$this->audit->record(AuditAction::TwoFactorEnabled, $user);

		return new JsonResponse(['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)]);
	}

	/**
	 * Remplace les codes de secours (les anciens ne valent plus).
	 *
	 * @param Request $request Requête portant un code valide.
	 * @return JsonResponse
	 */
	public function regenerateRecoveryCodes(Request $request): JsonResponse
	{
		$user = $this->user($request);
		$code = $this->validatedCode($request);
		abort_unless($user->hasTwoFactor(), 409);

		$this->requireValidCode($user, $code);
		$this->audit->record(AuditAction::RecoveryCodesRegenerated, $user);

		return new JsonResponse(['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)]);
	}

	/**
	 * Désactive la double authentification, sauf si la politique d'instance l'impose.
	 *
	 * @param Request $request Requête portant un code valide.
	 * @return Response
	 */
	public function destroy(Request $request): Response
	{
		$user = $this->user($request);
		$code = $this->validatedCode($request);
		abort_unless($user->hasTwoFactor(), 409);
		abort_if((bool) config('suivi.authentication.two_factor_required'), 403);

		$this->requireValidCode($user, $code);
		$user->forceFill([
			'two_factor_secret' => null,
			'two_factor_confirmed_at' => null,
			'two_factor_recovery_codes' => null,
		])->save();
		Log::notice('Double authentification désactivée.', ['utilisateur' => $user->id]);
		$this->audit->record(AuditAction::TwoFactorDisabled, $user);

		return response()->noContent();
	}

	/**
	 * Utilisateur connecté.
	 *
	 * @param Request $request Requête courante.
	 * @return User
	 */
	private function user(Request $request): User
	{
		/** @var User $user */
		$user = $request->user();

		return $user;
	}

	/**
	 * Code TOTP saisi (6 chiffres, espaces tolérés ; la forme est vérifiée ensuite).
	 *
	 * @param Request $request Requête courante.
	 * @return string
	 */
	private function validatedCode(Request $request): string
	{
		return (string) $request->validate(['code' => ['required', 'string', 'max:16']])['code'];
	}

	/**
	 * Lève une erreur de validation si le code TOTP est faux ou déjà utilisé. Un code
	 * faux compte dans le verrouillage du compte : une session volée ne permet pas de
	 * deviner un code pour désactiver ou renouveler.
	 *
	 * @param User $user Compte authentifié.
	 * @param string $code Code TOTP saisi.
	 * @return void
	 */
	private function requireValidCode(User $user, string $code): void
	{
		if ($this->lockout->blocks($user) || ! $this->twoFactor->verifyCode($user, $code)) {
			if (! $this->lockout->blocks($user)) {
				$this->lockout->recordFailure($user);
			}
			Log::warning('Code de double authentification refusé.', ['utilisateur' => $user->id]);

			throw ValidationException::withMessages(['code' => [__('auth.two_factor_invalid')]]);
		}
	}
}
