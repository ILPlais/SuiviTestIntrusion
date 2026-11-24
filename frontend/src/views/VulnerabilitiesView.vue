<!--
	Vulnérabilités d'un test (vulnerabilites-3, 11.09) : liste avec référence,
	phase, sévérité et composants ; création rapide d'une fiche (titre et phase)
	par l'équipe du test, la suite se complète sur la fiche.
-->
<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, getJson, postJson } from '../api/client'
import type { PenetrationTest } from '../api/penetrationTests'
import type { Vulnerability } from '../api/vulnerabilities'
import ActionIcon from '../components/ActionIcon.vue'
import LoadingThrobber from '../components/LoadingThrobber.vue'
import { formatScore } from '../utils/cvss31'

const props = defineProps<{
	/** Identifiant (ULID) du test. */
	id: string
}>()

const { t, locale } = useI18n()
const router = useRouter()

const test = ref<PenetrationTest | null>(null)
const vulnerabilities = ref<Vulnerability[]>([])
const loading = ref(false)
const missing = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const form = ref({ title: '', phase_id: '' })

/**
 * Charge le test et toutes les pages de ses vulnérabilités.
 *
 * @param id Identifiant du test.
 */
async function load(id: string): Promise<void> {
	test.value = null
	vulnerabilities.value = []
	missing.value = false
	error.value = null
	loading.value = true
	try {
		const received = (await getJson<{ data: PenetrationTest }>(`/tests/${encodeURIComponent(id)}`)).data
		const all: Vulnerability[] = []
		for (let page = 1, last = 1; page <= last; page++) {
			const response = await getJson<{ data: Vulnerability[], meta: { last_page: number } }>(
				`/tests/${encodeURIComponent(id)}/vulnerabilities?page=${page}&sort=reference&order=asc`,
			)
			all.push(...response.data)
			last = response.meta.last_page
		}
		if (id === props.id) {
			test.value = received
			vulnerabilities.value = all
			// Phase proposée par défaut : l'exploitation, où naissent la plupart des constats.
			form.value.phase_id = received.phases.find((phase) => phase.code === 'exploitation')?.id ?? received.phases[0]?.id ?? ''
		}
	} catch (caught) {
		if (id === props.id) {
			missing.value = caught instanceof ApiError && caught.status === 404
			error.value = missing.value ? null : t('auth.unexpected')
		}
	} finally {
		if (id === props.id) {
			loading.value = false
		}
	}
}

/** Crée la fiche puis l'ouvre pour la compléter. */
async function create(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		const created = (await postJson<{ data: Vulnerability }>(`/tests/${encodeURIComponent(props.id)}/vulnerabilities`, {
			title: form.value.title.trim(),
			phase_id: form.value.phase_id,
		})).data
		await router.push({ name: 'vulnerability', params: { id: created.id } })
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
		{{ t('vulnerabilities.testNotFound') }}
	</p>
	<p
		v-if="error"
		role="alert"
	>
		{{ error }}
	</p>
	<LoadingThrobber
		v-if="loading && !test"
		:label="t('common.loading')"
		visible-label
	/>
	<template v-if="test">
		<p>
			<RouterLink :to="{ name: 'test', params: { id: test.id } }">
				{{ t('vulnerabilities.back') }}
			</RouterLink>
		</p>
		<h2>{{ t('vulnerabilities.heading', { test: `${test.reference} — ${test.title}` }) }}</h2>

		<p v-if="vulnerabilities.length === 0">
			{{ t('vulnerabilities.empty') }}
		</p>
		<table v-else>
			<caption>{{ t('vulnerabilities.listCaption') }}</caption>
			<thead>
				<tr>
					<th scope="col">
						{{ t('vulnerabilities.reference') }}
					</th>
					<th scope="col">
						{{ t('vulnerabilities.title') }}
					</th>
					<th scope="col">
						{{ t('vulnerabilities.phase') }}
					</th>
					<th scope="col">
						{{ t('vulnerabilities.severity') }}
					</th>
					<th scope="col">
						{{ t('vulnerabilities.components') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="vulnerability in vulnerabilities"
					:key="vulnerability.id"
				>
					<th scope="row">
						<RouterLink :to="{ name: 'vulnerability', params: { id: vulnerability.id } }">
							{{ vulnerability.reference }}
						</RouterLink>
					</th>
					<td>{{ vulnerability.title }}</td>
					<td>{{ t(`tests.phaseNames.${vulnerability.phase.code}`) }}</td>
					<td>
						<template v-if="vulnerability.severity !== null">
							{{ t(`vulnerabilities.severities.${vulnerability.severity}`) }} {{ vulnerability.cvss_score !== null ? formatScore(vulnerability.cvss_score, locale) : '' }}
						</template>
						<template v-else>
							{{ t('vulnerabilities.unrated') }}
						</template>
					</td>
					<td>{{ vulnerability.scope_objects.map((object) => object.label).join(', ') || t('vulnerabilities.noComponent') }}</td>
				</tr>
			</tbody>
		</table>

		<form
			v-if="test.can_manage_scope"
			@submit.prevent="create"
		>
			<fieldset>
				<legend>{{ t('vulnerabilities.add') }}</legend>
				<label for="vulnerabilite-titre">{{ t('vulnerabilities.title') }}</label>
				<input
					id="vulnerabilite-titre"
					v-model="form.title"
					required
					maxlength="255"
				>
				<label for="vulnerabilite-phase">{{ t('vulnerabilities.phase') }}</label>
				<select
					id="vulnerabilite-phase"
					v-model="form.phase_id"
					required
				>
					<option
						v-for="phase in test.phases"
						:key="phase.id"
						:value="phase.id"
					>
						{{ t(`tests.phaseNames.${phase.code}`) }}
					</option>
				</select>
				<button
					type="submit"
					:disabled="busy"
				>
					<ActionIcon
						name="create"
						:busy="busy"
					/>{{ t('vulnerabilities.create') }}
				</button>
			</fieldset>
		</form>
	</template>
</template>
