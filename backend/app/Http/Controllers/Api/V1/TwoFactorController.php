<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
	public function __construct(private readonly TwoFactor $twoFactor)
	{
	}

	/**
	 * Démarre (ou recommence) l'enrôlement : nouveau secret et URL du QR code.
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
	 */
	public function confirm(Request $request): JsonResponse
	{
		$user = $this->user($request);
		$code = $this->validatedCode($request);
		abort_if($user->two_factor_secret === null || $user->hasTwoFactor(), 409);

		$this->requireValidCode($user, $code);
		$user->forceFill(['two_factor_confirmed_at' => now()])->save();

		return new JsonResponse(['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)]);
	}

	/**
	 * Remplace les codes de secours (les anciens ne valent plus).
	 */
	public function regenerateRecoveryCodes(Request $request): JsonResponse
	{
		$user = $this->user($request);
		$code = $this->validatedCode($request);
		abort_unless($user->hasTwoFactor(), 409);

		$this->requireValidCode($user, $code);

		return new JsonResponse(['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)]);
	}

	/**
	 * Désactive la double authentification, sauf si la politique d'instance l'impose.
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

		return response()->noContent();
	}

	/**
	 * Utilisateur connecté.
	 */
	private function user(Request $request): User
	{
		/** @var User $user */
		$user = $request->user();

		return $user;
	}

	/**
	 * Code TOTP saisi (6 chiffres, espaces tolérés ; la forme est vérifiée ensuite).
	 */
	private function validatedCode(Request $request): string
	{
		return (string) $request->validate(['code' => ['required', 'string', 'max:16']])['code'];
	}

	/**
	 * Lève une erreur de validation si le code TOTP est faux ou déjà utilisé.
	 */
	private function requireValidCode(User $user, string $code): void
	{
		if (! $this->twoFactor->verifyCode($user, $code)) {
			throw ValidationException::withMessages(['code' => [__('auth.two_factor_invalid')]]);
		}
	}
}
