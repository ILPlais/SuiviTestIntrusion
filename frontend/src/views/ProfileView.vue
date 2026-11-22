<!--
	Profil du compte connecté (engagement-1, 11.05, références « Testeur
	d'intrusion ») : nom, poste et présentation. L'adresse e-mail, qui entre dans
	la valeur d'authentification (13.03), n'est pas modifiable ici.
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, patchJson } from '../api/client'
import MarkdownField from '../components/MarkdownField.vue'
import { type CurrentUser, useAuthStore } from '../stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const form = ref({
	name: auth.user?.name ?? '',
	job_title: auth.user?.job_title ?? '',
	presentation: auth.user?.presentation ?? '',
})
const busy = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

/** Enregistre le profil et met à jour le compte affiché. */
async function save(): Promise<void> {
	busy.value = true
	error.value = null
	message.value = null
	try {
		auth.user = await patchJson<CurrentUser>('/user/profile', {
			name: form.value.name,
			job_title: form.value.job_title || null,
			presentation: form.value.presentation || null,
		})
		message.value = t('common.saved')
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<h2>{{ t('profile.heading') }}</h2>
	<p
		v-if="error"
		role="alert"
	>
		{{ error }}
	</p>
	<p
		v-if="message"
		role="status"
	>
		{{ message }}
	</p>
	<form @submit.prevent="save">
		<p>{{ t('profile.email', { email: auth.user?.email ?? '' }) }}</p>
		<label for="profil-nom">{{ t('profile.name') }}</label>
		<input
			id="profil-nom"
			v-model="form.name"
			required
			maxlength="255"
		>
		<label for="profil-poste">{{ t('profile.jobTitle') }}</label>
		<input
			id="profil-poste"
			v-model="form.job_title"
			maxlength="255"
		>
		<MarkdownField
			v-model="form.presentation"
			:label="t('profile.presentation')"
		/>
		<button
			type="submit"
			:disabled="busy"
		>
			{{ t('common.save') }}
		</button>
	</form>
</template>
