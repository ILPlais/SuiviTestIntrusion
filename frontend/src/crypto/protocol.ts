/**
 * Messages échangés avec le Worker crypto (13.04) : partagés par le Worker et
 * le fil principal, pour que chaque opération ait des entrées et des sorties
 * typées. Aucune sortie ne contient de clé privée déchiffrée ni de mot de passe.
 */
import type { VaultCopies } from './vault'

/** Motifs d'échec traduits par l'interface. */
export type CryptoErrorReason =
	| 'out_of_memory'
	| 'failed'
	| 'unsupported'
	| 'wrong_secret'
	| 's2k_too_costly'
	| 's2k_unsupported'
	| 'fingerprint_mismatch'
	| 'locked'

/** Contexte du sel de `auth` (13.03). */
interface AccountContext {
	instanceId: string
	email: string
}

/** Entrées et sorties de chaque opération du Worker. */
export interface CryptoOperations {
	auth: {
		input: AccountContext & { password: string }
		output: { auth: string }
	}
	enroll: {
		input: AccountContext & { password: string }
		output: { auth: string, recoveryKey: string, vault: VaultCopies }
	}
	unlock: {
		input: { passwordCopy: string, password: string }
		output: { fingerprint: string, outdated: boolean }
	}
	rotate: {
		input: AccountContext & { passwordCopy: string, oldPassword: string, newPassword: string }
		output: { currentAuth: string, auth: string, passwordCopy: string, fingerprint: string }
	}
	recover: {
		input: AccountContext & { recoveryCopy: string, recoveryKey: string, newPassword: string }
		output: { auth: string, recoveryKey: string, vault: VaultCopies }
	}
	status: {
		input: Record<string, never>
		output: { unlocked: boolean, fingerprint: string | null }
	}
}
