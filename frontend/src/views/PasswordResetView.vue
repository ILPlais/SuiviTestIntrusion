<!--
	Mot de passe oublié (identite-7, parcours de 13.03), par le lien émis par un
	administrateur. Hors de la navigation de l'application.

	1. Second facteur, si le compte en a un : la copie 2 de la clé privée n'est
	   livrée qu'ensuite.
	2. Clé de récupération et nouveau mot de passe : le Worker crypto ouvre la
	   copie 2, puis prépare une nouvelle copie 1, une nouvelle clé de
	   récupération et une nouvelle copie 2. La paire de clés ne change pas.
	3. Nouvelle clé de récupération : affichée une fois, ressaisie obligatoire ;
	   ensuite seulement, le nouvel `auth` et les copies partent au serveur.
-->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, getJson, postJson } from '../api/client'
import { CryptoError } from '../crypto'
import { useAuthStore, type Enrollment } from '../stores/auth'
import ActionIcon from '../components/ActionIcon.vue'
import NewPasswordFields from '../components/NewPasswordFields.vue'
import RecoveryKeyStep from '../components/RecoveryKeyStep.vue'

const props = defineProps<{ token: string }>()

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

/** Étapes du parcours, dans l'ordre. */
const STEPS = ['code', 'password', 'recovery'] as const

const account = ref<{ email: string, name: string, two_factor_required: boolean } | null>(null)
const invalidLink = ref(false)
const step = ref<typeof STEPS[number]>('code')
const code = ref('')
const copy = ref<{ recovery_copy: string, key_fingerprint: string, version: number } | null>(null)
const recoveryKey = ref('')
const password = ref('')
const passwordValid = ref(false)
const prepared = ref<Enrollment | null>(null)
const recoveryConfirmed = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

/**
 * Message lisible pour une erreur du parcours.
 *
 * @param caught Erreur levée.
 * @returns Le message traduit.
 */
function describe(caught: unknown): string {
	if (caught instanceof ApiError) {
		return caught.firstMessage()
	}
	if (caught instanceof CryptoError && (caught.reason === 'wrong_secret' || caught.reason === 'fingerprint_mismatch')) {
		return t('reset.wrongRecoveryKey')
	}
	if (caught instanceof CryptoError && caught.reason === 'out_of_memory') {
		return t('auth.outOfMemory')
	}

	return t('auth.unexpected')
}

/** Étape 1 : demande la copie 2, avec le code du second facteur s'il est exigé. */
async function open(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		copy.value = await postJson(`/auth/password-reset/${encodeURIComponent(props.token)}/open`, account.value?.two_factor_required ? { code: code.value } : {})
		step.value = 'password'
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		busy.value = false
	}
}

/** Étape 2 : ouvre la copie 2 et prépare les nouvelles copies ; le mot de passe est ensuite oublié. */
async function prepare(): Promise<void> {
	if (!account.value || !copy.value || !passwordValid.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		const result = await auth.prepareRecovery(account.value.email, copy.value.recovery_copy, recoveryKey.value, password.value)
		if (result.vault.fingerprint !== copy.value.key_fingerprint) {
			throw new CryptoError('fingerprint_mismatch')
		}
		prepared.value = result
		password.value = ''
		recoveryKey.value = ''
		step.value = 'recovery'
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		busy.value = false
	}
}

/** Étape 3 validée : enregistre le nouveau mot de passe, puis renvoie vers la connexion. */
async function finish(): Promise<void> {
	if (!prepared.value || !copy.value || !recoveryConfirmed.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		await auth.resetPassword(props.token, prepared.value, copy.value.version)
		prepared.value = null
		await router.push({ name: 'login', query: { reset: '1' } })
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		busy.value = false
	}
}

onMounted(async () => {
	try {
		account.value = await getJson(`/auth/password-reset/${encodeURIComponent(props.token)}`)
		// Sans second facteur, la copie 2 est demandée tout de suite.
		if (!account.value?.two_factor_required) {
			await open()
		}
	} catch {
		invalidLink.value = true
	}
})
</script>

<template>
	<section aria-labelledby="reset-heading">
		<h2 id="reset-heading">
			{{ t('reset.heading') }}
		</h2>
		<p
			v-if="invalidLink"
			role="alert"
		>
			{{ t('reset.invalidLink') }}
		</p>
		<template v-else-if="account">
			<ol
				class="steps"
				:aria-label="t('enrollment.stepsLabel')"
			>
				<li
					v-for="(name, index) in STEPS"
					:key="name"
					:aria-current="step === name ? 'step' : undefined"
				>
					{{ index + 1 }}. {{ t(`reset.steps.${name}`) }}
				</li>
			</ol>

			<form
				v-if="step === 'code' && account.two_factor_required"
				@submit.prevent="open"
			>
				<p>{{ t('reset.codeIntro') }}</p>
				<label for="reset-code">{{ t('auth.twoFactorCode') }}</label>
				<input
					id="reset-code"
					v-model="code"
					inputmode="numeric"
					autocomplete="one-time-code"
					required
				>
				<button
					type="submit"
					:disabled="busy"
				>
					<ActionIcon
						name="check"
						:busy="busy"
					/>{{ t('auth.verify') }}
				</button>
			</form>

			<form
				v-else-if="step === 'password'"
				@submit.prevent="prepare"
			>
				<p>{{ t('reset.intro', { name: account.name, email: account.email }) }}</p>
				<label for="reset-recovery-key">{{ t('reset.recoveryKey') }}</label>
				<input
					id="reset-recovery-key"
					v-model="recoveryKey"
					autocomplete="off"
					spellcheck="false"
					required
				>
				<NewPasswordFields
					v-model="password"
					v-model:valid="passwordValid"
					id-prefix="reset"
					:email="account.email"
				/>
				<button
					type="submit"
					:disabled="!passwordValid || recoveryKey === '' || busy"
				>
					<ActionIcon
						name="key"
						:busy="busy"
					/>{{ busy ? t('reset.preparing') : t('enrollment.next') }}
				</button>
			</form>

			<form
				v-else-if="step === 'recovery' && prepared"
				@submit.prevent="finish"
			>
				<RecoveryKeyStep
					v-model:confirmed="recoveryConfirmed"
					:recovery-key="prepared.recoveryKey"
					:email="account.email"
				/>
				<button
					type="submit"
					:disabled="!recoveryConfirmed || busy"
				>
					<ActionIcon
						name="check"
						:busy="busy"
					/>{{ t('reset.finish') }}
				</button>
			</form>

			<p
				v-if="error"
				role="alert"
			>
				{{ error }}
			</p>
		</template>
	</section>
</template>
