<!--
	Tests d'intrusion accessibles (engagement-2 ; mise en page de la maquette 01
	de 15.01, socle-9) : filtres (recherche, organisation, statut), tableau avec
	le statut et le risque en badges ; un chef de projet ou un administrateur
	crée un test pour une organisation qu'il dirige (PenetrationTestPolicy côté
	API), depuis le bouton « Nouveau test ».
-->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, getJson, postJson } from '../api/client'
import type { Organisation } from '../api/organisations'
import { TEST_EXPOSURES, TEST_STATUSES, TEST_TYPES, type PenetrationTest, type PenetrationTestSummary, type TestExposure, type TestStatus, type TestType } from '../api/penetrationTests'
import { severityTag, statusTag } from '../api/testContext'
import { useAuthStore } from '../stores/auth'
import { formatScore } from '../utils/cvss31'
import { fromLocalInput } from '../utils/dates'
import ActionIcon from '../components/ActionIcon.vue'
import LoadingThrobber from '../components/LoadingThrobber.vue'
import NfIcon from '../components/NfIcon.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const tests = ref<PenetrationTestSummary[] | null>(null)
const organisations = ref<Organisation[]>([])
const form = ref({
	tenant_id: '',
	reference: '',
	title: '',
	type: 'grey_box' as TestType,
	exposure: 'internal' as TestExposure,
	planned_start: '',
	planned_end: '',
})
const creating = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

/** Filtres de la liste (maquette 01) ; « open » masque les tests validés. */
const filters = ref({ search: '', tenant: '', status: 'open' as TestStatus | 'open' | 'all' })

/** Organisations où l'utilisateur peut créer un test (même règle que l'API). */
const managed = computed(() => organisations.value.filter(
	(organisation) => auth.user?.is_instance_admin === true || organisation.role === 'project_manager',
))

/** Tests retenus par les filtres. */
const visible = computed(() => {
	const search = filters.value.search.trim().toLocaleLowerCase(locale.value)
	return (tests.value ?? []).filter((test) => {
		const status = filters.value.status
		const statusMatches = status === 'all' || (status === 'open' ? test.status !== 'validated' : test.status === status)
		const searchMatches = search === '' || `${test.reference} ${test.title}`.toLocaleLowerCase(locale.value).includes(search)
		return statusMatches && searchMatches && (filters.value.tenant === '' || test.tenant.id === filters.value.tenant)
	})
})

/**
 * Date courte (« 1 déc. 2026 »), ou null.
 *
 * @param iso Date ISO 8601, ou null.
 * @returns La date mise en forme.
 */
function shortDate(iso: string | null): string | null {
	return iso === null ? null : new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}

/**
 * Période prévue d'un test.
 *
 * @param test Test de la liste.
 * @returns « 1 déc. 2026 → 5 déc. 2026 », ou « non renseigné ».
 */
function period(test: PenetrationTestSummary): string {
	const start = shortDate(test.planned_start)
	const end = shortDate(test.planned_end)
	return start || end ? `${start ?? '…'} → ${end ?? '…'}` : t('common.none')
}

/** Charge la liste des tests, les plus récents d'abord, et les organisations. */
async function load(): Promise<void> {
	try {
		const [list, tenants] = await Promise.all([
			getJson<{ data: PenetrationTestSummary[] }>('/tests?limit=200'),
			getJson<{ data: Organisation[] }>('/tenants?sort=name&order=asc&limit=200'),
		])
		tests.value = list.data
		organisations.value = tenants.data
		form.value.tenant_id = managed.value[0]?.id ?? ''
	} catch {
		// Liste vide plutôt qu'un « Chargement… » permanent sous l'alerte.
		tests.value = []
		error.value = t('auth.unexpected')
	}
}

/** Crée le test, dates converties en UTC, puis ouvre sa synthèse. */
async function create(): Promise<void> {
	busy.value = true
	error.value = null
	try {
		const created = await postJson<{ data: PenetrationTest }>('/tests', {
			...form.value,
			planned_start: fromLocalInput(form.value.planned_start),
			planned_end: fromLocalInput(form.value.planned_end),
		})
		await router.push({ name: 'test', params: { id: created.data.id } })
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		busy.value = false
	}
}

onMounted(load)
</script>

<template>
	<div class="toolbar">
		<h2>{{ t('tests.heading') }}</h2>
		<span class="spacer" />
		<button
			v-if="managed.length > 0"
			type="button"
			:aria-expanded="creating"
			@click="creating = !creating"
		>
			<ActionIcon name="create" />{{ t('tests.newTest') }}
		</button>
	</div>
	<p
		v-if="error"
		role="alert"
	>
		{{ error }}
	</p>

	<form
		v-if="creating && managed.length > 0"
		@submit.prevent="create"
	>
		<fieldset class="panel">
			<legend>{{ t('tests.newTest') }}</legend>
			<label for="test-organisation">{{ t('tests.organisation') }}</label>
			<select
				id="test-organisation"
				v-model="form.tenant_id"
				required
			>
				<option
					v-for="organisation in managed"
					:key="organisation.id"
					:value="organisation.id"
				>
					{{ organisation.name }}
				</option>
			</select>
			<label for="test-reference">{{ t('tests.reference') }}</label>
			<input
				id="test-reference"
				v-model="form.reference"
				required
				maxlength="64"
			>
			<label for="test-titre">{{ t('tests.title') }}</label>
			<input
				id="test-titre"
				v-model="form.title"
				required
				maxlength="255"
			>
			<label for="test-type">{{ t('tests.type') }}</label>
			<select
				id="test-type"
				v-model="form.type"
			>
				<option
					v-for="type in TEST_TYPES"
					:key="type"
					:value="type"
				>
					{{ t(`tests.types.${type}`) }}
				</option>
			</select>
			<label for="test-exposition">{{ t('tests.exposure') }}</label>
			<select
				id="test-exposition"
				v-model="form.exposure"
			>
				<option
					v-for="exposure in TEST_EXPOSURES"
					:key="exposure"
					:value="exposure"
				>
					{{ t(`tests.exposures.${exposure}`) }}
				</option>
			</select>
			<label for="test-debut">{{ t('tests.plannedStart') }}</label>
			<input
				id="test-debut"
				v-model="form.planned_start"
				type="datetime-local"
			>
			<label for="test-fin">{{ t('tests.plannedEnd') }}</label>
			<input
				id="test-fin"
				v-model="form.planned_end"
				type="datetime-local"
			>
			<p>
				<button
					type="submit"
					:disabled="busy"
				>
					<ActionIcon
						name="create"
						:busy="busy"
					/>{{ t('tests.create') }}
				</button>
			</p>
		</fieldset>
	</form>

	<section
		class="panel soft"
		aria-labelledby="tests-filtres"
	>
		<h3
			id="tests-filtres"
			class="panel-title"
		>
			{{ t('tests.filters') }}
		</h3>
		<div class="toolbar">
			<label for="tests-recherche"><NfIcon name="search" />{{ t('tests.search') }}</label>
			<input
				id="tests-recherche"
				v-model="filters.search"
				type="search"
			>
			<label for="tests-filtre-organisation">{{ t('tests.filterOrganisation') }}</label>
			<select
				id="tests-filtre-organisation"
				v-model="filters.tenant"
			>
				<option value="">
					{{ t('tests.allOrganisations') }}
				</option>
				<option
					v-for="organisation in organisations"
					:key="organisation.id"
					:value="organisation.id"
				>
					{{ organisation.name }}
				</option>
			</select>
			<label for="tests-filtre-statut">{{ t('tests.filterStatus') }}</label>
			<select
				id="tests-filtre-statut"
				v-model="filters.status"
			>
				<option value="open">
					{{ t('tests.openTests') }}
				</option>
				<option value="all">
					{{ t('tests.allTests') }}
				</option>
				<option
					v-for="status in TEST_STATUSES"
					:key="status"
					:value="status"
				>
					{{ t(`tests.statuses.${status}`) }}
				</option>
			</select>
		</div>
	</section>

	<p v-if="tests === null">
		<LoadingThrobber
			:label="t('common.loading')"
			visible-label
		/>
	</p>
	<p v-else-if="visible.length === 0">
		{{ t('tests.empty') }}
	</p>
	<table v-else>
		<caption>{{ t('tests.listCount', { shown: visible.length, total: tests.length }) }}</caption>
		<thead>
			<tr>
				<th scope="col">
					{{ t('tests.reference') }}
				</th>
				<th scope="col">
					{{ t('tests.title') }}
				</th>
				<th scope="col">
					{{ t('tests.organisation') }}
				</th>
				<th scope="col">
					{{ t('tests.status') }}
				</th>
				<th scope="col">
					{{ t('tests.period') }}
				</th>
				<th scope="col">
					{{ t('tests.risk') }}
				</th>
			</tr>
		</thead>
		<tbody>
			<tr
				v-for="test in visible"
				:key="test.id"
			>
				<td>
					<RouterLink :to="{ name: 'test', params: { id: test.id } }">
						{{ test.reference }}
					</RouterLink>
				</td>
				<td>{{ test.title }}</td>
				<td>{{ test.tenant.name }}</td>
				<td>
					<span
						class="tag"
						:class="statusTag(test.status)"
					>{{ t(`tests.statuses.${test.status}`) }}</span>
				</td>
				<td>{{ period(test) }}</td>
				<td>
					<span
						v-if="test.risk.max_score !== null && test.risk.max_severity !== null"
						class="tag"
						:class="severityTag(test.risk.max_severity)"
					>{{ t(`vulnerabilities.severities.${test.risk.max_severity}`) }} {{ formatScore(test.risk.max_score, locale) }}</span>
					<template v-else>
						{{ t('tests.noRisk') }}
					</template>
				</td>
			</tr>
		</tbody>
	</table>
</template>
