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
}

/** Identifiant public de l'instance, lu une fois (sel de `auth`). */
let instanceId: string | null = null

async function getInstanceId(): Promise<string> {
	instanceId ??= (await getJson<{ instance_id: string }>('/instance')).instance_id

	return instanceId
}

/** Store Pinia de la session. */
export const useAuthStore = defineStore('auth', () => {
	/** Compte connecté ; `undefined` tant que la session n'a pas été vérifiée. */
	const user = ref<CurrentUser | null | undefined>(undefined)

	/** Vérifie la session auprès de l'API ; ne lève pas d'exception. */
	async function fetchUser(): Promise<CurrentUser | null> {
		try {
			user.value = await getJson<CurrentUser>('/user')
		} catch {
			user.value = null
		}

		return user.value
	}

	/** Connexion : dérive `auth`, puis ouvre la session. Lève une ApiError ou une CryptoError. */
	async function login(email: string, password: string): Promise<void> {
		const auth = await computeAuth(password, await getInstanceId(), email)
		user.value = await postJson<CurrentUser>('/auth/login', { email, auth })
	}

	/** Activation : enregistre `auth` pour le compte du lien. */
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

	return { user, fetchUser, login, activate, logout }
})
