<!--
	Affiche un texte Markdown (engagement-1) : HTML produit et assaini par le
	module de rendu (13.04), inséré sans v-html, par la politique Trusted Types
	`sti-render`.
-->
<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { markdownToTrustedHtml } from './markdown'

const props = defineProps<{
	/** Texte Markdown à afficher. */
	source: string | null
}>()

const container = ref<HTMLElement | null>(null)

/** Réécrit le contenu du conteneur à partir du Markdown courant. */
function render(): void {
	if (container.value) {
		// TrustedHTML ou chaîne assainie : le type DOM n'accepte que string.
		container.value.innerHTML = markdownToTrustedHtml(props.source ?? '') as string
	}
}

onMounted(render)
watch(() => props.source, render)
</script>

<template>
	<div
		ref="container"
		class="markdown"
	/>
</template>
