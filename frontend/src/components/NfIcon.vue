<!--
	Icône Nerd Fonts décorative (socle-8). Le glyphe passe par l'attribut
	data-glyph, affiché en CSS (content: attr(data-glyph)) : il n'entre pas dans
	le texte de la page, et aria-hidden le retire aussi de l'arbre d'accessibilité.
	Le sens est toujours porté par le texte voisin.
-->
<script setup lang="ts">
import { computed } from 'vue'
import { glyph, type IconName } from '../icons'

const props = defineProps<{
	/** Nom d'usage de l'icône (src/icons/glyphs.json). */
	name: IconName
}>()

const character = computed(() => glyph(props.name))
</script>

<template>
	<span
		class="icon"
		aria-hidden="true"
		:data-glyph="character"
	/>
</template>
