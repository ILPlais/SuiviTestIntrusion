<!--
	Fiche d'une organisation cliente (engagement-1, 11.05) : lecture pour tous ses
	membres ; modification par un administrateur ou un chef de projet de
	l'organisation ; suppression par un administrateur (TenantPolicy côté API).
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, deleteJson, getJson, patchJson } from '../api/client'
import type { Organisation } from '../api/organisations'
import MarkdownField from '../components/MarkdownField.vue'
import MarkdownView from '../render/MarkdownView.vue'
import { useAuthStore } from '../stores/auth'
import ActionIcon from '../components/ActionIcon.vue'

const props = defineProps<{
	/** Identifiant (ULID) de l'organisation. */
	id: string
}>()

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const organisation = ref<Organisation | null>(null)
const missing = ref(false)
const form = ref({ name: '', legal_name: '', presentation: '' })
const editing = ref(false)
const busy = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

/** Vrai si l'utilisateur peut modifier la fiche (même règle que l'API). */
const canEdit = computed(() => auth.user?.is_instance_admin === true || organisation.value?.role === 'project_manager')

/**
 * Charge la fiche ; une organisation étrangère ou inconnue donne « introuvable ».
 *
 * @param id Identifiant de l'organisation.
 */
async function load(id: string): Promise<void> {
	missing.value = false
	try {
		organisation.value = (await getJson<{ data: Organisation }>(`/tenants/${encodeURIComponent(id)}`)).data
	} catch (caught) {
		organisation.value = null
		missing.value = caught instanceof ApiError && caught.status === 404
		error.value = missing.value ? null : t('auth.unexpected')
	}
}

/** Ouvre le formulaire, prérempli avec la fiche courante. */
function edit(): void {
	if (organisation.value) {
		form.value = {
			name: organisation.value.name,
			legal_name: organisation.value.legal_name ?? '',
			presentation: organisation.value.presentation ?? '',
		}
		editing.value = true
		message.value = null
	}
}

/** Enregistre la fiche ; les champs vides sont enregistrés comme absents. */
async function save(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		organisation.value = (await patchJson<{ data: Organisation }>(`/tenants/${encodeURIComponent(props.id)}`, {
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

/** Supprime l'organisation après confirmation, puis revient à la liste. */
async function remove(): Promise<void> {
	if (!organisation.value || !globalThis.confirm(t('organisations.confirmDelete', { name: organisation.value.name }))) {
		return
	}
	busy.value = true
	try {
		await deleteJson(`/tenants/${encodeURIComponent(props.id)}`)
		await router.push({ name: 'organisations' })
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		busy.value = false
	}
}

watch(() => props.id, load, { immediate: true })
</script>

<template>
	<p v-if="missing">
		{{ t('organisations.notFound') }}
	</p>
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
	<template v-if="organisation">
		<h2>{{ organisation.name }}</h2>
		<form
			v-if="editing"
			@submit.prevent="save"
		>
			<label for="organisation-nom">{{ t('organisations.name') }}</label>
			<input
				id="organisation-nom"
				v-model="form.name"
				required
				maxlength="255"
			>
			<label for="organisation-raison-sociale">{{ t('organisations.legalName') }}</label>
			<input
				id="organisation-raison-sociale"
				v-model="form.legal_name"
				maxlength="255"
			>
			<MarkdownField
				v-model="form.presentation"
				:label="t('organisations.presentation')"
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
		<template v-else>
			<dl>
				<dt>{{ t('organisations.legalName') }}</dt>
				<dd>{{ organisation.legal_name ?? t('common.none') }}</dd>
				<dt>{{ t('organisations.role') }}</dt>
				<dd>{{ organisation.role ? t(`roles.${organisation.role}`) : t('roles.administrator') }}</dd>
			</dl>
			<h3>{{ t('organisations.presentation') }}</h3>
			<MarkdownView :source="organisation.presentation" />
			<button
				v-if="canEdit"
				type="button"
				@click="edit"
			>
				<ActionIcon name="edit" />{{ t('common.edit') }}
			</button>
			<button
				v-if="auth.user?.is_instance_admin"
				type="button"
				:disabled="busy"
				@click="remove"
			>
				<ActionIcon
					name="delete"
					:busy="busy"
				/>{{ t('organisations.delete') }}
			</button>
		</template>
	</template>
</template>
