<!--
	Sécurité du compte (identite-3) : activation de la double authentification
	(QR code dessiné localement dans un canvas, clé affichée pour une saisie à la
	main), codes de secours affichés une seule fois, renouvellement et désactivation.
	Le QR code n'utilise ni service tiers ni URL data: (CSP de 13.04).
-->
<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { ApiError, deleteJson, postJson } from '../api/client'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const enrollment = ref<{ secret: string, otpauth_url: string } | null>(null)
const recoveryCodes = ref<string[] | null>(null)
const code = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)

/**
 * Exécute une action de l'API en gérant l'état d'attente et les erreurs.
 *
 * @param action Appel à l'API.
 */
async function run(action: () => Promise<void>): Promise<void> {
	busy.value = true
	error.value = null
	try {
		await action()
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		code.value = ''
		busy.value = false
	}
}

/**
 * Démarre l'enrôlement et dessine le QR code.
 *
 * @returns La promesse de l'action.
 */
function start(): Promise<void> {
	return run(async () => {
		enrollment.value = await postJson<{ secret: string, otpauth_url: string }>('/user/two-factor')
		await nextTick()
		if (canvas.value) {
			await QRCode.toCanvas(canvas.value, enrollment.value.otpauth_url, { width: 220, margin: 2 })
		}
	})
}

/**
 * Confirme l'enrôlement par un premier code ; affiche les codes de secours.
 *
 * @returns La promesse de l'action.
 */
function confirm(): Promise<void> {
	return run(async () => {
		const response = await postJson<{ recovery_codes: string[] }>('/user/two-factor/confirm', { code: code.value })
		recoveryCodes.value = response.recovery_codes
		enrollment.value = null
		await auth.fetchUser()
	})
}

/**
 * Remplace les codes de secours.
 *
 * @returns La promesse de l'action.
 */
function regenerate(): Promise<void> {
	return run(async () => {
		recoveryCodes.value = (await postJson<{ recovery_codes: string[] }>('/user/two-factor/recovery-codes', { code: code.value })).recovery_codes
	})
}

/**
 * Désactive la double authentification.
 *
 * @returns La promesse de l'action.
 */
function disable(): Promise<void> {
	return run(async () => {
		await deleteJson('/user/two-factor', { code: code.value })
		await auth.fetchUser()
	})
}
</script>

<template>
	<section aria-labelledby="security-heading">
		<h2 id="security-heading">
			{{ t('security.heading') }}
		</h2>
		<p
			v-if="auth.user?.two_factor_setup_required"
			role="status"
		>
			{{ t('security.required') }}
		</p>

		<div v-if="recoveryCodes">
			<h3>{{ t('security.recoveryHeading') }}</h3>
			<p>{{ t('security.recoveryIntro') }}</p>
			<ul data-testid="recovery-codes">
				<li
					v-for="recovery in recoveryCodes"
					:key="recovery"
				>
					<code>{{ recovery }}</code>
				</li>
			</ul>
			<button
				type="button"
				@click="recoveryCodes = null"
			>
				{{ t('security.done') }}
			</button>
		</div>

		<template v-else-if="auth.user?.two_factor_enabled">
			<p>{{ t('security.enabled') }}</p>
			<p>
				<label for="security-code">{{ t('security.codeForAction') }}</label>
				<input
					id="security-code"
					v-model="code"
					inputmode="numeric"
					autocomplete="one-time-code"
				>
			</p>
			<button
				type="button"
				:disabled="busy || !code"
				@click="regenerate"
			>
				{{ t('security.regenerate') }}
			</button>
			<button
				type="button"
				:disabled="busy || !code"
				@click="disable"
			>
				{{ t('security.disable') }}
			</button>
		</template>

		<template v-else>
			<p>{{ t('security.disabled') }}</p>
			<button
				v-if="!enrollment"
				type="button"
				:disabled="busy"
				@click="start"
			>
				{{ t('security.enable') }}
			</button>
			<form
				v-else
				@submit.prevent="confirm"
			>
				<p>{{ t('security.scan') }}</p>
				<canvas
					ref="canvas"
					role="img"
					:aria-label="t('security.qrLabel')"
				/>
				<p>
					{{ t('security.secretLabel') }}
					<code data-testid="totp-secret">{{ enrollment.secret }}</code>
				</p>
				<p>
					<label for="security-confirm">{{ t('security.confirmCode') }}</label>
					<input
						id="security-confirm"
						v-model="code"
						inputmode="numeric"
						autocomplete="one-time-code"
						required
					>
				</p>
				<button
					type="submit"
					:disabled="busy"
				>
					{{ t('security.confirm') }}
				</button>
			</form>
		</template>

		<p
			v-if="error"
			role="alert"
		>
			{{ error }}
		</p>
	</section>
</template>
