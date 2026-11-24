<!--
	Lien à usage unique émis par le serveur (identite-7) : invitation ou
	réinitialisation du mot de passe. Aucun e-mail n'est envoyé en V1 (13.06) :
	le lien s'affiche ici, une seule fois, pour être transmis au titulaire par un
	autre canal. Le serveur n'en garde que l'empreinte et ne peut pas le réafficher.
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ActionIcon from './ActionIcon.vue'

defineProps<{
	/** Lien complet à transmettre. */
	link: string
	/** Texte qui précède le lien (destinataire, durée de validité). */
	intro: string
}>()

const { t } = useI18n()
const copied = ref(false)

/**
 * Copie le lien dans le presse-papiers.
 *
 * @param link Lien à copier.
 */
async function copy(link: string): Promise<void> {
	try {
		await navigator.clipboard.writeText(link)
		copied.value = true
	} catch {
		// Presse-papiers refusé (contexte non sécurisé, permission) : le lien reste sélectionnable.
		copied.value = false
	}
}
</script>

<template>
	<div
		class="panel issued-link"
		role="status"
	>
		<p>{{ intro }}</p>
		<input
			:value="link"
			readonly
			data-testid="issued-link"
			:aria-label="t('accounts.linkLabel')"
			@focus="($event.target as HTMLInputElement).select()"
		>
		<button
			type="button"
			@click="copy(link)"
		>
			<ActionIcon name="save" />{{ copied ? t('accounts.copied') : t('accounts.copyLink') }}
		</button>
		<small>{{ t('accounts.linkOnce') }}</small>
	</div>
</template>
