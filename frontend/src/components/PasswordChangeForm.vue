<!--
	Changement de mot de passe (ged-chiffree-1, rotation de 13.02) : le Worker
	crypto vérifie le mot de passe actuel en ouvrant la copie 1, la rechiffre
	avec le nouveau et calcule les deux valeurs d'authentification. Seule la
	copie 1 change : fichiers, clé publique et clé de récupération restent.
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '../api/client'
import { CryptoError } from '../crypto'
import { useAuthStore } from '../stores/auth'
import ActionIcon from './ActionIcon.vue'
import NewPasswordFields from './NewPasswordFields.vue'

const { t } = useI18n()
const auth = useAuthStore()

const current = ref('')
const password = ref('')
const valid = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const done = ref(false)

/**
 * Message lisible pour un échec du changement.
 *
 * @param caught Erreur levée.
 * @returns Le message traduit.
 */
function describe(caught: unknown): string {
	if (caught instanceof CryptoError) {
		if (caught.reason === 'wrong_secret') {
			return t('vault.wrongCurrent')
		}
		if (caught.reason === 'out_of_memory') {
			return t('auth.outOfMemory')
		}
	}
	if (caught instanceof ApiError) {
		return caught.firstMessage()
	}

	return t('auth.unexpected')
}

/** Change le mot de passe, puis efface les champs. */
async function submit(): Promise<void> {
	busy.value = true
	error.value = null
	done.value = false
	try {
		await auth.changePassword(current.value, password.value)
		done.value = true
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		current.value = ''
		password.value = ''
		busy.value = false
	}
}
</script>

<template>
	<h3>{{ t('vault.passwordHeading') }}</h3>
	<p>{{ t('vault.passwordIntro') }}</p>
	<form @submit.prevent="submit">
		<p>
			<label for="password-current">{{ t('vault.currentPassword') }}</label>
			<input
				id="password-current"
				v-model="current"
				type="password"
				autocomplete="current-password"
				required
			>
		</p>
		<NewPasswordFields
			v-model="password"
			v-model:valid="valid"
			id-prefix="password-change"
			:email="auth.user?.email ?? ''"
		/>
		<p
			v-if="error"
			role="alert"
		>
			{{ error }}
		</p>
		<p
			v-if="done"
			role="status"
		>
			{{ t('vault.changed') }}
		</p>
		<button
			type="submit"
			:disabled="busy || !current || !valid"
		>
			<ActionIcon
				name="save"
				:busy="busy"
			/>{{ busy ? t('vault.changing') : t('vault.change') }}
		</button>
	</form>
</template>
