<!--
	Fiche de l'entreprise prestataire (engagement-1, 11.05) : lue par tout
	utilisateur connecté, modifiée par un administrateur d'instance.
-->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, getJson, putJson } from '../api/client'
import type { CompanyProfile } from '../api/organisations'
import MarkdownField from '../components/MarkdownField.vue'
import MarkdownView from '../render/MarkdownView.vue'
import { useAuthStore } from '../stores/auth'
import ActionIcon from '../components/ActionIcon.vue'

const { t } = useI18n()
const auth = useAuthStore()

const profile = ref<CompanyProfile | null>(null)
const form = ref({ name: '', legal_name: '', presentation: '' })
const editing = ref(false)
const busy = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

/** Charge la fiche de l'entreprise. */
async function load(): Promise<void> {
	try {
		profile.value = (await getJson<{ data: CompanyProfile }>('/company-profile')).data
	} catch {
		error.value = t('auth.unexpected')
	}
}

/** Ouvre le formulaire, prérempli avec la fiche courante. */
function edit(): void {
	form.value = {
		name: profile.value?.name ?? '',
		legal_name: profile.value?.legal_name ?? '',
		presentation: profile.value?.presentation ?? '',
	}
	editing.value = true
	message.value = null
}

/** Remplace la fiche ; les champs vides sont enregistrés comme absents. */
async function save(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		profile.value = (await putJson<{ data: CompanyProfile }>('/company-profile', {
			name: form.value.name,
			legal_name: form.value.legal_name || null,
			presentation: form.value.presentation || null,
		})).data
		editing.value = false
		message.value = t('common.saved')
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		busy.value = false
	}
}

onMounted(load)
</script>

<template>
	<h2>{{ t('company.heading') }}</h2>
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
	<form
		v-if="editing"
		@submit.prevent="save"
	>
		<label for="entreprise-nom">{{ t('company.name') }}</label>
		<input
			id="entreprise-nom"
			v-model="form.name"
			required
			maxlength="255"
		>
		<label for="entreprise-raison-sociale">{{ t('company.legalName') }}</label>
		<input
			id="entreprise-raison-sociale"
			v-model="form.legal_name"
			maxlength="255"
		>
		<MarkdownField
			v-model="form.presentation"
			:label="t('company.presentation')"
		/>
		<button
			type="submit"
			:disabled="busy"
		>
			<ActionIcon
				name="save"
				:busy="busy"
			/>{{ t('common.save') }}
		</button>
		<button
			type="button"
			@click="editing = false"
		>
			<ActionIcon name="cancel" />{{ t('common.cancel') }}
		</button>
	</form>
	<template v-else-if="profile">
		<p v-if="!profile.name">
			{{ t('company.empty') }}
		</p>
		<dl v-else>
			<dt>{{ t('company.name') }}</dt>
			<dd>{{ profile.name }}</dd>
			<dt>{{ t('company.legalName') }}</dt>
			<dd>{{ profile.legal_name ?? t('common.none') }}</dd>
		</dl>
		<MarkdownView :source="profile.presentation" />
		<button
			v-if="auth.user?.is_instance_admin"
			type="button"
			@click="edit"
		>
			<ActionIcon name="edit" />{{ t('common.edit') }}
		</button>
	</template>
</template>
