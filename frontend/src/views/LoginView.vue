<!--
	Écran de connexion (identite-1, identite-3) : adresse et mot de passe, puis, si
	la double authentification est active, code TOTP ou code de secours. Le mot de
	passe est transmis au Worker crypto, qui en dérive `auth` ; il n'est jamais envoyé.
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../api/client'
import { CryptoError } from '../crypto'
import { useAuthStore } from '../stores/auth'
import ActionIcon from '../components/ActionIcon.vue'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const step = ref<'credentials' | 'second-factor'>('credentials')
const useRecovery = ref(false)
const code = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const activated = route.query.activated === '1'

/**
 * Message lisible pour une erreur de connexion.
 *
 * @param caught Erreur levée par la connexion.
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

/** Revient à la page demandée (chemin interne seulement), ou à l'accueil. */
async function goOn(): Promise<void> {
	const redirect = route.query.redirect
	const target = typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/'
	await router.push(target)
}

/** Premier temps : adresse et mot de passe. */
async function submitCredentials(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		const needsSecondFactor = await auth.login(email.value, password.value)
		password.value = ''
		if (needsSecondFactor) {
			step.value = 'second-factor'
		} else {
			await goOn()
		}
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		busy.value = false
	}
}

/** Second temps : code TOTP ou code de secours. */
async function submitCode(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		await auth.verifyTwoFactor(useRecovery.value ? { recovery_code: code.value } : { code: code.value })
		await goOn()
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		code.value = ''
		busy.value = false
	}
}
</script>

<template>
	<section aria-labelledby="login-heading">
		<h2 id="login-heading">
			{{ step === 'credentials' ? t('auth.loginHeading') : t('auth.twoFactorHeading') }}
		</h2>
		<p
			v-if="activated && step === 'credentials'"
			role="status"
		>
			{{ t('auth.activated') }}
		</p>
		<form
			v-if="step === 'credentials'"
			@submit.prevent="submitCredentials"
		>
			<p>
				<label for="login-email">{{ t('auth.email') }}</label>
				<input
					id="login-email"
					v-model="email"
					type="email"
					autocomplete="username"
					required
				>
			</p>
			<p>
				<label for="login-password">{{ t('auth.password') }}</label>
				<input
					id="login-password"
					v-model="password"
					type="password"
					autocomplete="current-password"
					required
				>
			</p>
			<p
				v-if="error"
				role="alert"
			>
				{{ error }}
			</p>
			<button
				type="submit"
				:disabled="busy"
			>
				<ActionIcon
					name="login"
					:busy="busy"
				/>{{ busy ? t('auth.checking') : t('auth.login') }}
			</button>
		</form>
		<form
			v-else
			@submit.prevent="submitCode"
		>
			<p v-if="!useRecovery">
				{{ t('auth.twoFactorPrompt') }}
			</p>
			<p>
				<label for="login-code">{{ useRecovery ? t('auth.recoveryCode') : t('auth.twoFactorCode') }}</label>
				<input
					id="login-code"
					v-model="code"
					:inputmode="useRecovery ? 'text' : 'numeric'"
					autocomplete="one-time-code"
					required
				>
			</p>
			<p
				v-if="error"
				role="alert"
			>
				{{ error }}
			</p>
			<button
				type="submit"
				:disabled="busy"
			>
				<ActionIcon
					name="check"
					:busy="busy"
				/>{{ t('auth.verify') }}
			</button>
			<button
				type="button"
				@click="useRecovery = !useRecovery"
			>
				<ActionIcon name="recovery" />{{ useRecovery ? t('auth.useTotpCode') : t('auth.useRecoveryCode') }}
			</button>
		</form>
	</section>
</template>
