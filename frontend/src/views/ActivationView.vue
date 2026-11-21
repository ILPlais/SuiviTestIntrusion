<!--
	Activation d'un compte par son lien (identite-1) : choix du mot de passe selon
	la politique de 13.03 (contrôlée ici, le serveur ne voyant jamais le mot de
	passe), indicateur de robustesse, puis envoi de `auth` seulement.
-->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, getJson } from '../api/client'
import { checkPassword, MIN_LENGTH, type PolicyResult } from '../crypto/passwordPolicy'
import { useAuthStore } from '../stores/auth'

const props = defineProps<{ token: string }>()

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const account = ref<{ email: string, name: string } | null>(null)
const invalidLink = ref(false)
const password = ref('')
const confirmation = ref('')
const policy = ref<PolicyResult | null>(null)
const busy = ref(false)
const error = ref<string | null>(null)

const mismatch = computed(() => confirmation.value !== '' && confirmation.value !== password.value)
const canSubmit = computed(() => policy.value?.valid === true && confirmation.value === password.value && !busy.value)

onMounted(async () => {
	try {
		account.value = await getJson<{ email: string, name: string }>(`/auth/activation/${encodeURIComponent(props.token)}`)
	} catch {
		invalidLink.value = true
	}
})

// Contrôle de la politique à chaque frappe ; seul le dernier résultat compte.
let check = 0
watch(password, async (value) => {
	const current = ++check
	const result = value === '' ? null : await checkPassword(value, account.value?.email ?? '')
	if (current === check) {
		policy.value = result
	}
})

/** Enregistre le mot de passe (sa valeur dérivée), puis renvoie vers la connexion. */
async function submit(): Promise<void> {
	if (!account.value || !canSubmit.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		await auth.activate(props.token, account.value.email, password.value)
		password.value = ''
		confirmation.value = ''
		await router.push({ name: 'login', query: { activated: '1' } })
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
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
		<form
			v-else-if="account"
			@submit.prevent="submit"
		>
			<p>{{ t('auth.activationIntro', { name: account.name, email: account.email }) }}</p>
			<p>
				<label for="activation-password">{{ t('auth.newPassword') }}</label>
				<input
					id="activation-password"
					v-model="password"
					type="password"
					autocomplete="new-password"
					aria-describedby="activation-policy"
					required
				>
			</p>
			<div
				id="activation-policy"
				aria-live="polite"
			>
				<p>{{ t('password.rule', { min: MIN_LENGTH }) }}</p>
				<template v-if="policy">
					<p>{{ t('password.strength', { level: t(`password.levels.${policy.score}`) }) }}</p>
					<ul v-if="policy.problems.length">
						<li
							v-for="problem in policy.problems"
							:key="problem"
						>
							{{ t(`password.problems.${problem}`) }}
						</li>
					</ul>
				</template>
			</div>
			<p>
				<label for="activation-confirmation">{{ t('auth.confirmPassword') }}</label>
				<input
					id="activation-confirmation"
					v-model="confirmation"
					type="password"
					autocomplete="new-password"
					required
				>
			</p>
			<p
				v-if="mismatch"
				role="alert"
			>
				{{ t('password.mismatch') }}
			</p>
			<p
				v-if="error"
				role="alert"
			>
				{{ error }}
			</p>
			<button
				type="submit"
				:disabled="!canSubmit"
			>
				{{ busy ? t('auth.checking') : t('auth.activate') }}
			</button>
		</form>
	</section>
</template>
