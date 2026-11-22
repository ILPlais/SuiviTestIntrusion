<!--
	Organisations clientes accessibles (engagement-1, identite-5) : liste avec le
	rôle de l'utilisateur dans chacune ; un administrateur d'instance en crée.
-->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, getJson, postJson } from '../api/client'
import type { Organisation } from '../api/organisations'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const organisations = ref<Organisation[] | null>(null)
const name = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

/** Charge la première tranche des organisations, triées par nom. */
async function load(): Promise<void> {
	try {
		organisations.value = (await getJson<{ data: Organisation[] }>('/tenants?sort=name&order=asc&limit=200')).data
	} catch {
		error.value = t('auth.unexpected')
	}
}

/** Crée une organisation puis ouvre sa fiche pour la compléter. */
async function create(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		const created = await postJson<{ data: Organisation }>('/tenants', { name: name.value })
		await router.push({ name: 'organisation', params: { id: created.data.id } })
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		busy.value = false
	}
}

onMounted(load)
</script>

<template>
	<h2>{{ t('organisations.heading') }}</h2>
	<p
		v-if="error"
		role="alert"
	>
		{{ error }}
	</p>
	<p v-if="organisations === null">
		{{ t('common.loading') }}
	</p>
	<p v-else-if="organisations.length === 0">
		{{ t('organisations.empty') }}
	</p>
	<ul v-else>
		<li
			v-for="organisation in organisations"
			:key="organisation.id"
		>
			<RouterLink :to="{ name: 'organisation', params: { id: organisation.id } }">
				{{ organisation.name }}
			</RouterLink>
			<span v-if="organisation.role">{{ t('organisations.roleSuffix', { role: t(`roles.${organisation.role}`) }) }}</span>
		</li>
	</ul>

	<form
		v-if="auth.user?.is_instance_admin"
		@submit.prevent="create"
	>
		<h3>{{ t('organisations.create') }}</h3>
		<label for="nouvelle-organisation">{{ t('organisations.name') }}</label>
		<input
			id="nouvelle-organisation"
			v-model="name"
			required
			maxlength="255"
		>
		<button
			type="submit"
			:disabled="busy"
		>
			{{ t('organisations.create') }}
		</button>
	</form>
</template>
