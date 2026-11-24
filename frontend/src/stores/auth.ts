/**
 * Session de l'utilisateur (identite-1, ged-chiffree-1) : compte connecté,
 * connexion, activation avec coffre de clés, changement de mot de passe,
 * réinitialisation par la clé de récupération (identite-7), déconnexion, et
 * état du coffre (clé privée déverrouillée ou non).
 *
 * Le mot de passe saisi n'est jamais envoyé : le Worker crypto en dérive la
 * valeur d'authentification `auth` (13.03), seule transmise à l'API, et s'en
 * sert pour ouvrir la copie 1 de la clé privée, qui reste dans le Worker de
 * session (13.04). Entre les deux temps d'une connexion avec second facteur,
 * le mot de passe est gardé dans une variable du module, hors de l'état
 * réactif, puis effacé.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { ApiError, getJson, postJson, putJson } from '../api/client'
import { computeAuth, enroll, lockSession, recover, rotate, unlockSession, type VaultCopies } from '../crypto'

/** Compte connecté, tel que le renvoie GET /api/v1/user. */
export interface CurrentUser {
	id: number
	name: string
	email: string
	is_instance_admin: boolean
	/** Poste (profil de testeur, engagement-1). */
	job_title: string | null
	/** Présentation en Markdown (engagement-1). */
	presentation: string | null
	/** Double authentification confirmée (identite-3). */
	two_factor_enabled: boolean
	/** La politique d'instance impose un second facteur que le compte n'a pas encore. */
	two_factor_setup_required: boolean
	/** Empreinte de la paire de clés ; nulle pour un compte enrôlé avant le coffre (ged-chiffree-1). */
	key_fingerprint: string | null
	/** Peut inviter : administrateur, ou chef de projet pour ses clients finaux (identite-7). */
	can_invite: boolean
}

/** Coffre servi par GET /api/v1/user/vault (copie 1 seulement). */
interface ServedVault {
	public_key: string
	key_fingerprint: string
	password_copy: string
	version: number
}

/** Enrôlement préparé dans le Worker, en attente de la ressaisie de la clé de récupération. */
export interface Enrollment {
	auth: string
	recoveryKey: string
	vault: VaultCopies
}

/** Identifiant public de l'instance, lu une fois (sel de `auth`). */
let instanceId: string | null = null

/** Mot de passe gardé entre les deux temps d'une connexion avec second facteur. */
let pendingPassword: string | null = null

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

	/** Clé privée déverrouillée dans le Worker de session. */
	const vaultUnlocked = ref(false)

	/** Le déverrouillage a échoué après une connexion réussie (copie illisible, mémoire). */
	const vaultError = ref<string | null>(null)

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
	 * Déverrouille la clé privée avec le mot de passe ; sans coffre, ne fait rien.
	 * Une erreur est retenue dans `vaultError` sans faire échouer la connexion.
	 *
	 * @param password Mot de passe saisi.
	 */
	async function unlockVault(password: string): Promise<void> {
		vaultError.value = null
		if (!user.value?.key_fingerprint) {
			return
		}
		try {
			const vault = await getJson<ServedVault>('/user/vault')
			const { fingerprint } = await unlockSession(vault.password_copy, password)
			// La clé servie doit être celle du compte : un serveur qui substituerait
			// une autre copie serait détecté ici.
			if (fingerprint !== user.value.key_fingerprint) {
				lockSession()
				throw new Error('fingerprint_mismatch')
			}
			vaultUnlocked.value = true
		} catch (caught) {
			vaultUnlocked.value = false
			vaultError.value = caught instanceof Error ? caught.message : 'failed'
			throw caught
		}
	}

	/**
	 * Termine une connexion : compte connecté, puis déverrouillage du coffre.
	 *
	 * @param current Compte renvoyé par l'API.
	 * @param password Mot de passe saisi.
	 */
	async function openSession(current: CurrentUser, password: string): Promise<void> {
		user.value = current
		try {
			await unlockVault(password)
		} catch {
			// La session est ouverte ; l'écran Sécurité propose de réessayer.
		}
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
			pendingPassword = password

			return true
		}
		await openSession(response, password)

		return false
	}

	/**
	 * Connexion, second temps : code TOTP ou code de secours (identite-3).
	 *
	 * @param answer Code TOTP ou code de secours saisi.
	 */
	async function verifyTwoFactor(answer: { code: string } | { recovery_code: string }): Promise<void> {
		const current = await postJson<CurrentUser>('/auth/two-factor', answer)
		const password = pendingPassword
		pendingPassword = null
		if (password === null) {
			user.value = current
		} else {
			await openSession(current, password)
		}
	}

	/** Abandonne une connexion en attente du second facteur : le mot de passe est oublié. */
	function cancelLogin(): void {
		pendingPassword = null
	}

	/**
	 * Enrôlement, premier temps (13.03) : `auth`, paire de clés, clé de
	 * récupération et copies 1 et 2, calculés dans le Worker crypto. Rien n'est
	 * envoyé avant la ressaisie de la clé de récupération.
	 *
	 * @param email Adresse du compte.
	 * @param password Mot de passe choisi.
	 * @returns L'enrôlement à confirmer.
	 */
	async function prepareEnrollment(email: string, password: string): Promise<Enrollment> {
		return enroll(password, await getInstanceId(), email)
	}

	/**
	 * Enrôlement, second temps : active le compte du lien avec `auth` et le coffre.
	 * La clé de récupération n'est pas envoyée.
	 *
	 * @param token Jeton du lien d'activation.
	 * @param enrollment Enrôlement préparé.
	 */
	async function activate(token: string, enrollment: Enrollment): Promise<void> {
		await postJson('/auth/activate', {
			token,
			auth: enrollment.auth,
			public_key: enrollment.vault.publicKey,
			key_fingerprint: enrollment.vault.fingerprint,
			password_copy: enrollment.vault.passwordCopy,
			recovery_copy: enrollment.vault.recoveryCopy,
		})
	}

	/**
	 * Réinitialisation du mot de passe (identite-7, 13.03), premier temps : ouvre
	 * la copie 2 avec la clé de récupération dans un Worker éphémère et prépare le
	 * nouveau mot de passe, la nouvelle clé de récupération et les nouvelles copies.
	 * L'empreinte doit rester celle du compte : c'est la même paire de clés.
	 *
	 * @param email Adresse du compte (sel de `auth`).
	 * @param recoveryCopy Copie 2, chiffrée par la clé de récupération.
	 * @param recoveryKey Clé de récupération saisie (casse et tirets ignorés).
	 * @param newPassword Nouveau mot de passe, conforme à la politique.
	 * @returns L'enrôlement à confirmer, avec la nouvelle clé de récupération.
	 */
	async function prepareRecovery(email: string, recoveryCopy: string, recoveryKey: string, newPassword: string): Promise<Enrollment> {
		return recover(recoveryCopy, recoveryKey, newPassword, await getInstanceId(), email)
	}

	/**
	 * Réinitialisation, second temps : envoie le nouvel `auth` et les deux
	 * nouvelles copies ; la nouvelle clé de récupération n'est pas envoyée.
	 *
	 * @param token Jeton du lien de réinitialisation.
	 * @param recovery Réinitialisation préparée.
	 * @param version Version de la copie lue (verrou optimiste).
	 */
	async function resetPassword(token: string, recovery: Enrollment, version: number): Promise<void> {
		await postJson('/auth/password-reset', {
			token,
			auth: recovery.auth,
			key_fingerprint: recovery.vault.fingerprint,
			password_copy: recovery.vault.passwordCopy,
			recovery_copy: recovery.vault.recoveryCopy,
			version,
		})
	}

	/**
	 * Change le mot de passe (rotation de 13.02) : la copie 1 est rechiffrée dans le
	 * Worker ; ni la clé publique, ni la copie 2, ni les fichiers ne changent. Si une
	 * rotation faite ailleurs a changé la version entre-temps (409), on relit la
	 * copie et on recommence une fois.
	 *
	 * @param currentPassword Mot de passe actuel.
	 * @param newPassword Nouveau mot de passe.
	 */
	async function changePassword(currentPassword: string, newPassword: string): Promise<void> {
		const account = user.value
		if (!account) {
			return
		}
		const instance = await getInstanceId()

		if (!account.key_fingerprint) {
			const [currentAuth, auth] = [await computeAuth(currentPassword, instance, account.email), await computeAuth(newPassword, instance, account.email)]
			await putJson('/user/password', { current_auth: currentAuth, auth })

			return
		}

		for (let attempt = 1; ; attempt++) {
			const vault = await getJson<ServedVault>('/user/vault')
			const rotated = await rotate(vault.password_copy, currentPassword, newPassword, instance, account.email)
			try {
				await putJson('/user/password', { current_auth: rotated.currentAuth, auth: rotated.auth, password_copy: rotated.passwordCopy, version: vault.version })

				return
			} catch (caught) {
				if (!(caught instanceof ApiError && caught.status === 409) || attempt >= 2) {
					throw caught
				}
			}
		}
	}

	/** Déconnexion : la clé est effacée (Worker arrêté) et la session oubliée, même si l'API ne répond pas. */
	async function logout(): Promise<void> {
		lockSession()
		vaultUnlocked.value = false
		pendingPassword = null
		try {
			await postJson('/auth/logout')
		} finally {
			user.value = null
		}
	}

	return { user, vaultUnlocked, vaultError, fetchUser, login, verifyTwoFactor, cancelLogin, unlockVault, prepareEnrollment, activate, prepareRecovery, resetPassword, changePassword, logout }
})
