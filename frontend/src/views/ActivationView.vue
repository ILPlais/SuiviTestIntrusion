<!--
	Activation d'un compte par son lien (identite-1, ged-chiffree-1, écran 17 de
	15.01) : parcours en trois étapes, sans la navigation de l'application.

	1. Mot de passe : politique de 13.03 contrôlée ici, le serveur ne voyant
	   jamais le mot de passe.
	2. Paire de clés : générée dans le Worker crypto, avec la clé de récupération
	   et les deux copies chiffrées de la clé privée (13.02, 13.03).
	3. Clé de récupération : affichée une fois, ressaisie obligatoire ; ensuite
	   seulement, `auth` et le coffre partent au serveur et le compte est activé.

	La double authentification se configure après la première connexion (écran Sécurité).
-->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, getJson } from '../api/client'
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
const STEPS = ['password', 'keys', 'recovery'] as const

const account = ref<{ email: string, name: string } | null>(null)
const invalidLink = ref(false)
const step = ref<typeof STEPS[number]>('password')
const password = ref('')
const passwordValid = ref(false)
const enrollment = ref<Enrollment | null>(null)
const recoveryConfirmed = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
	try {
		account.value = await getJson<{ email: string, name: string }>(`/auth/activation/${encodeURIComponent(props.token)}`)
	} catch {
		invalidLink.value = true
	}
})

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
	if (caught instanceof CryptoError && caught.reason === 'out_of_memory') {
		return t('auth.outOfMemory')
	}

	return t('auth.unexpected')
}

/** Étape 2 : génère la paire de clés et ses copies ; le mot de passe est ensuite oublié. */
async function generateKeys(): Promise<void> {
	if (!account.value || !passwordValid.value) {
		return
	}
	busy.value = true
	error.value = null
	step.value = 'keys'
	try {
		enrollment.value = await auth.prepareEnrollment(account.value.email, password.value)
		password.value = ''
		step.value = 'recovery'
	} catch (caught) {
		error.value = describe(caught)
		step.value = 'password'
	} finally {
		busy.value = false
	}
}

/** Étape 3 validée : active le compte, puis renvoie vers la connexion. */
async function activate(): Promise<void> {
	if (!enrollment.value || !recoveryConfirmed.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		await auth.activate(props.token, enrollment.value)
		enrollment.value = null
		await router.push({ name: 'login', query: { activated: '1' } })
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<section aria-labelledby="activation-heading">
		<h2 id="activation-heading">
			{{ t('auth.activationHeading') }}
		</h2>
		<p
			v-if="invalidLink"
			role="alert"
		>
			{{ t('auth.invalidLink') }}
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
					{{ index + 1 }}. {{ t(`enrollment.steps.${name}`) }}
				</li>
			</ol>

			<form
				v-if="step === 'password'"
				@submit.prevent="generateKeys"
			>
				<p>{{ t('auth.activationIntro', { name: account.name, email: account.email }) }}</p>
				<NewPasswordFields
					v-model="password"
					v-model:valid="passwordValid"
					id-prefix="activation"
					:email="account.email"
				/>
				<button
					type="submit"
					:disabled="!passwordValid || busy"
				>
					<ActionIcon name="key" />{{ t('enrollment.next') }}
				</button>
			</form>

			<p
				v-else-if="step === 'keys'"
				role="status"
			>
				<ActionIcon
					name="key"
					:busy="true"
				/>{{ t('enrollment.generating') }}
			</p>

			<form
				v-else-if="enrollment"
				@submit.prevent="activate"
			>
				<RecoveryKeyStep
					v-model:confirmed="recoveryConfirmed"
					:recovery-key="enrollment.recoveryKey"
					:email="account.email"
				/>
				<button
					type="submit"
					:disabled="!recoveryConfirmed || busy"
				>
					<ActionIcon
						name="check"
						:busy="busy"
					/>{{ busy ? t('auth.checking') : t('auth.activate') }}
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
