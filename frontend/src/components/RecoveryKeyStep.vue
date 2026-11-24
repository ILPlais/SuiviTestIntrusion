<!--
	Clé de récupération (écran 17 de 15.01, 13.03, ged-chiffree-1) : affichée une
	seule fois en groupes de quatre caractères, copiable et téléchargeable en
	.txt, puis ressaisie pour confirmer qu'elle a été conservée. Casse, espaces
	et tirets sont ignorés à la ressaisie. Elle n'est jamais envoyée au serveur.
-->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRecoveryKey, normalizeRecoveryKey, RECOVERY_KEY_LENGTH } from '../crypto/recoveryKey'
import ActionIcon from './ActionIcon.vue'

const props = defineProps<{
	/** Clé de récupération canonique, tirée par le Worker crypto. */
	recoveryKey: string
	/** Adresse du compte, rappelée dans le fichier téléchargé. */
	email: string
}>()

/** Vrai quand la ressaisie correspond à la clé. */
const confirmed = defineModel<boolean>('confirmed', { required: true })

const { t } = useI18n()
const typed = ref('')
const copied = ref(false)

const formatted = computed(() => formatRecoveryKey(props.recoveryKey))
const missing = computed(() => Math.max(0, RECOVERY_KEY_LENGTH - normalizeRecoveryKey(typed.value).length))
const matches = computed(() => normalizeRecoveryKey(typed.value) === props.recoveryKey)
const status = computed(() => {
	if (typed.value === '') {
		return ''
	}
	if (matches.value) {
		return t('recovery.matches')
	}

	return missing.value > 0 ? t('recovery.missing', { count: missing.value }, missing.value) : t('recovery.mismatch')
})

/** Met à jour la confirmation à chaque frappe. */
function onInput(): void {
	confirmed.value = matches.value
}

/** Copie la clé dans le presse-papiers. */
async function copy(): Promise<void> {
	await navigator.clipboard.writeText(formatted.value)
	copied.value = true
}

/**
 * Propose la clé en fichier texte. Le blob est téléchargé, jamais ouvert dans un
 * onglet : un document `blob:` hériterait de l'origine de l'application (13.04).
 */
function download(): void {
	const text = `${t('recovery.fileHeading')}\n${props.email}\n\n${formatted.value}\n\n${t('recovery.fileAdvice')}\n`
	const url = URL.createObjectURL(new Blob([text], { type: 'application/octet-stream' }))
	const link = document.createElement('a')
	link.href = url
	link.download = 'cle-de-recuperation.txt'
	link.click()
	URL.revokeObjectURL(url)
}
</script>

<template>
	<h3>{{ t('recovery.heading') }}</h3>
	<p>{{ t('recovery.intro') }}</p>
	<p>
		<code
			class="recovery-key"
			data-testid="recovery-key"
		>{{ formatted }}</code>
	</p>
	<p>
		<button
			type="button"
			@click="copy"
		>
			<ActionIcon :name="copied ? 'check' : 'key'" />{{ copied ? t('recovery.copied') : t('recovery.copy') }}
		</button>
		<button
			type="button"
			@click="download"
		>
			<ActionIcon name="save" />{{ t('recovery.download') }}
		</button>
	</p>
	<p>{{ t('recovery.storage') }}</p>
	<p>
		<label for="recovery-confirmation">{{ t('recovery.retype') }}</label>
		<input
			id="recovery-confirmation"
			v-model="typed"
			autocomplete="off"
			spellcheck="false"
			aria-describedby="recovery-status"
			@input="onInput"
		>
	</p>
	<p
		id="recovery-status"
		aria-live="polite"
	>
		{{ t('recovery.rule') }} {{ status }}
	</p>
</template>
