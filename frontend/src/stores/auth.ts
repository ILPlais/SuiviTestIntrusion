/**
 * Session de l'utilisateur (identite-1) : compte connecté, connexion,
 * activation et déconnexion.
 *
 * Le mot de passe saisi n'est jamais envoyé : le Worker crypto en dérive la
 * valeur d'authentification `auth` (13.03), seule transmise à l'API.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getJson, postJson } from '../api/client'
import { computeAuth } from '../crypto'

/** Compte connecté, tel que le renvoie GET /api/v1/user. */
export interface CurrentUser {
	id: number
	name: string
	email: string
	is_instance_admin: boolean
	/** Double authentification confirmée (identite-3). */
	two_factor_enabled: boolean
	/** La politique d'instance impose un second facteur que le compte n'a pas encore. */
	two_factor_setup_required: boolean
}

/** Identifiant public de l'instance, lu une fois (sel de `auth`). */
let instanceId: string | null = null

/**
 * Identifiant public de l'instance, demandé à l'API au premier besoin.
 *
 * @returns L'identifiant de l'instance.
 */
async function getInstanceId(): Promise<string> {
	instanceId ??= (await getJson<{ instance_id: string }>('/instance')).instance_id

	return instanceId
}

/** Store Pinia de la session. */
export const useAuthStore = defineStore('auth', () => {
	/** Compte connecté ; `undefined` tant que la session n'a pas été vérifiée. */
	const user = ref<CurrentUser | null | undefined>(undefined)

	/**
	 * Vérifie la session auprès de l'API ; ne lève pas d'exception.
	 *
	 * @returns Le compte connecté, ou null sans session.
	 */
	async function fetchUser(): Promise<CurrentUser | null> {
		try {
			user.value = await getJson<CurrentUser>('/user')
		} catch {
			user.value = null
		}

		return user.value
	}

	/**
	 * Connexion, premier temps : dérive `auth` et l'envoie. Renvoie vrai si un second
	 * facteur est demandé ; sinon la session est ouverte. Lève une ApiError ou une CryptoError.
	 *
	 * @param email Adresse saisie.
	 * @param password Mot de passe saisi.
	 * @returns Vrai si un second facteur est demandé.
	 */
	async function login(email: string, password: string): Promise<boolean> {
		const auth = await computeAuth(password, await getInstanceId(), email)
		const response = await postJson<CurrentUser | { two_factor_required: true }>('/auth/login', { email, auth })
		if ('two_factor_required' in response) {
			return true
		}
		user.value = response

		return false
	}

	/**
	 * Connexion, second temps : code TOTP ou code de secours (identite-3).
	 *
	 * @param answer Code TOTP ou code de secours saisi.
	 */
	async function verifyTwoFactor(answer: { code: string } | { recovery_code: string }): Promise<void> {
		user.value = await postJson<CurrentUser>('/auth/two-factor', answer)
	}

	/**
	 * Activation : enregistre `auth` pour le compte du lien.
	 *
	 * @param token Jeton du lien d'activation.
	 * @param email Adresse du compte.
	 * @param password Mot de passe choisi.
	 */
	async function activate(token: string, email: string, password: string): Promise<void> {
		const auth = await computeAuth(password, await getInstanceId(), email)
		await postJson('/auth/activate', { token, auth })
	}

	/** Déconnexion ; la session locale est oubliée même si l'API ne répond pas. */
	async function logout(): Promise<void> {
		try {
			await postJson('/auth/logout')
		} finally {
			user.value = null
		}
	}

	return { user, fetchUser, login, verifyTwoFactor, activate, logout }
})
