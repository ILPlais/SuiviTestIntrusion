<!--
	Choix d'un nouveau mot de passe (identite-1, ged-chiffree-1) : saisie,
	contrôle de la politique de 13.03 à chaque frappe (le serveur ne voyant
	jamais le mot de passe, c'est ici qu'elle s'applique), indicateur de
	robustesse, puis confirmation. Partagé par l'activation et le changement de
	mot de passe.
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { checkPassword, MIN_LENGTH, type PolicyResult } from '../crypto/passwordPolicy'

const props = defineProps<{
	/** Préfixe des identifiants des champs, unique dans la page. */
	idPrefix: string
	/** Adresse du compte, pour refuser un mot de passe qui la reprend. */
	email: string
}>()

/** Mot de passe choisi, valide ou non. */
const password = defineModel<string>({ required: true })

/** Vrai quand le mot de passe respecte la politique et que la confirmation concorde. */
const valid = defineModel<boolean>('valid', { required: true })

const { t } = useI18n()
const confirmation = ref('')
const policy = ref<PolicyResult | null>(null)

const mismatch = computed(() => confirmation.value !== '' && confirmation.value !== password.value)

// Contrôle de la politique à chaque frappe ; seul le dernier résultat compte.
let check = 0
watch(password, async (value) => {
	const current = ++check
	const result = value === '' ? null : await checkPassword(value, props.email)
	if (current === check) {
		policy.value = result
	}
	// Champ vidé par le parent après usage : la confirmation suit.
	if (value === '') {
		confirmation.value = ''
	}
})

watch([policy, confirmation, password], () => {
	valid.value = policy.value?.valid === true && confirmation.value === password.value
})
</script>

<template>
	<p>
		<label :for="`${idPrefix}-password`">{{ t('auth.newPassword') }}</label>
		<input
			:id="`${idPrefix}-password`"
			v-model="password"
			type="password"
			autocomplete="new-password"
			:aria-describedby="`${idPrefix}-policy`"
			required
		>
	</p>
	<div
		:id="`${idPrefix}-policy`"
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
		<label :for="`${idPrefix}-confirmation`">{{ t('auth.confirmPassword') }}</label>
		<input
			:id="`${idPrefix}-confirmation`"
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
</template>
