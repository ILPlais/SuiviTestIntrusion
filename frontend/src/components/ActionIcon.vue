<!--
	Icône d'un bouton d'action (socle-8) : l'icône Nerd Fonts de l'action, ou le
	spinner WebTUI tant que l'action est en cours. Les deux sont décoratifs : le
	texte du bouton porte son sens, et le bouton désactivé signale l'attente.
-->
<script setup lang="ts">
import type { IconName } from '../icons'
import NfIcon from './NfIcon.vue'

defineProps<{
	/** Icône de l'action (src/icons/glyphs.json). */
	name: IconName
	/** Vrai pendant l'exécution de l'action : le spinner remplace l'icône. */
	busy?: boolean
}>()
</script>

<template>
	<span
		v-if="busy"
		is-="spinner"
		variant-="dots"
		aria-hidden="true"
	/>
	<NfIcon
		v-else
		:name="name"
	/>
</template>
