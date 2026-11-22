<!--
	Champ de texte riche (engagement-1) : saisie en Markdown, avec un aperçu rendu
	par le module de rendu. Le texte reste du Markdown jusque dans l'API.
-->
<script setup lang="ts">
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import MarkdownView from '../render/MarkdownView.vue'

defineProps<{
	/** Libellé du champ. */
	label: string
	/** Nombre maximal de caractères. */
	maxlength?: number
}>()

/** Texte Markdown saisi. */
const model = defineModel<string>({ default: '' })

const { t } = useI18n()
const id = useId()
const preview = ref(false)
</script>

<template>
	<div class="markdown-field">
		<label :for="id">{{ label }}</label>
		<p :id="`${id}-aide`">
			{{ t('markdown.help') }}
		</p>
		<textarea
			v-if="!preview"
			:id="id"
			v-model="model"
			rows="8"
			:maxlength="maxlength ?? 20000"
			:aria-describedby="`${id}-aide`"
		/>
		<MarkdownView
			v-else
			:source="model"
		/>
		<button
			type="button"
			:aria-pressed="preview"
			@click="preview = !preview"
		>
			{{ preview ? t('markdown.edit') : t('markdown.preview') }}
		</button>
	</div>
</template>
