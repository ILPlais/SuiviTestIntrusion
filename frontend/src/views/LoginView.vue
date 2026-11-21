<!--
	Écran de connexion (identite-1) : adresse et mot de passe. Le mot de passe est
	transmis au Worker crypto, qui en dérive `auth` ; il n'est jamais envoyé.
	Le calcul prend environ une seconde : le bouton l'annonce pendant ce temps.
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../api/client'
import { CryptoError } from '../crypto'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const activated = route.query.activated === '1'

/** Connecte l'utilisateur, puis revient à la page demandée (ou à l'accueil). */
async function submit(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		await auth.login(email.value, password.value)
		password.value = ''
		// Seul un chemin interne est suivi : pas de redirection ouverte vers un autre site.
		const redirect = route.query.redirect
		const target = typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/'
		await router.push(target)
	} catch (caught) {
		if (caught instanceof ApiError) {
			error.value = caught.firstMessage()
		} else if (caught instanceof CryptoError && caught.reason === 'out_of_memory') {
			error.value = t('auth.outOfMemory')
		} else {
			error.value = t('auth.unexpected')
		}
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<section aria-labelledby="login-heading">
		<h2 id="login-heading">
			{{ t('auth.loginHeading') }}
		</h2>
		<p
			v-if="activated"
			role="status"
		>
			{{ t('auth.activated') }}
		</p>
		<form @submit.prevent="submit">
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
				{{ busy ? t('auth.checking') : t('auth.login') }}
			</button>
		</form>
	</section>
</template>
