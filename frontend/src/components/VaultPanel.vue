<!--
	État du coffre de clés (ged-chiffree-1) : clé privée déverrouillée ou non pour
	cette session, empreinte de la clé, et ressaisie du mot de passe quand le
	Worker de session a été arrêté (rechargement de la page, verrouillage).
-->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import ActionIcon from './ActionIcon.vue'

const { t } = useI18n()
const auth = useAuthStore()

const password = ref('')
const busy = ref(false)
const failed = ref(false)

/** Empreinte en groupes de quatre caractères, plus facile à comparer à voix haute. */
const fingerprint = computed(() => (auth.user?.key_fingerprint?.match(/.{1,4}/g) ?? []).join(' '))

/** Déverrouille la clé dans le Worker de session ; le mot de passe est ensuite effacé. */
async function unlock(): Promise<void> {
	busy.value = true
	failed.value = false
	try {
		await auth.unlockVault(password.value)
	} catch {
		failed.value = true
	} finally {
		password.value = ''
		busy.value = false
	}
}
</script>

<template>
	<h3>{{ t('vault.heading') }}</h3>
	<p v-if="!auth.user?.key_fingerprint">
		{{ t('vault.none') }}
	</p>
	<template v-else>
		<p>
			{{ t('vault.fingerprint') }} <code data-testid="key-fingerprint">{{ fingerprint }}</code>
		</p>
		<p
			v-if="auth.vaultUnlocked"
			role="status"
		>
			{{ t('vault.unlocked') }}
		</p>
		<form
			v-else
			@submit.prevent="unlock"
		>
			<p
				v-if="auth.vaultLockReason"
				role="status"
			>
				{{ t(`vault.lockReasons.${auth.vaultLockReason}`) }}
			</p>
			<p>{{ t('vault.locked') }}</p>
			<p>
				<label for="vault-password">{{ t('auth.password') }}</label>
				<input
					id="vault-password"
					v-model="password"
					type="password"
					autocomplete="current-password"
					required
				>
			</p>
			<p
				v-if="failed"
				role="alert"
			>
				{{ t('vault.unlockFailed') }}
			</p>
			<button
				type="submit"
				:disabled="busy || !password"
			>
				<ActionIcon
					name="key"
					:busy="busy"
				/>{{ t('vault.unlock') }}
			</button>
		</form>
		<details>
			<summary>{{ t('vault.limitsHeading') }}</summary>
			<ul>
				<li>{{ t('vault.limits.idle') }}</li>
				<li>{{ t('vault.limits.sleep') }}</li>
				<li>{{ t('vault.limits.memory') }}</li>
				<li>{{ t('vault.limits.reload') }}</li>
			</ul>
		</details>
	</template>
</template>
