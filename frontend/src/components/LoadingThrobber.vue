<!--
	Throbber (socle-8) : spinner WebTUI animé, décoratif, accompagné d'un
	libellé. Le libellé est masqué visuellement par défaut (lu par les lecteurs
	d'écran), ou affiché à côté du spinner. Le composant n'annonce rien de
	lui-même : la zone qui l'affiche porte aria-busy ou un message role="status".
-->
<script setup lang="ts">
defineProps<{
	/** Ce qui est en cours (ex. « Chargement… »). */
	label: string
	/** Vrai pour afficher le libellé à côté du spinner. */
	visibleLabel?: boolean
}>()
</script>

<template>
	<span class="throbber">
		<span
			is-="spinner"
			variant-="dots"
			aria-hidden="true"
		/>
		<span :class="{ 'visually-hidden': !visibleLabel }">{{ label }}</span>
	</span>
</template>
