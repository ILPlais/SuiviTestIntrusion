<!--
	Fiche d'un test d'intrusion (engagement-2, 11.05, 11.08) : lecture pour tous
	les membres de l'organisation, avec les phases et l'historique des statuts ;
	modification et changement de statut par un chef de projet de l'organisation
	ou un administrateur ; suppression par un administrateur.
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, deleteJson, getJson, patchJson, postJson } from '../api/client'
import { TEST_EXPOSURES, TEST_STATUSES, TEST_TYPES, type PenetrationTest, type StatusChange, type TestExposure, type TestStatus, type TestType } from '../api/penetrationTests'
import { useAuthStore } from '../stores/auth'
import { formatDateTime, fromLocalInput, toLocalInput } from '../utils/dates'
import ActionIcon from '../components/ActionIcon.vue'
import RiskSummary from '../components/RiskSummary.vue'

const props = defineProps<{
	/** Identifiant (ULID) du test. */
	id: string
}>()

const { t, locale } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const test = ref<PenetrationTest | null>(null)
const missing = ref(false)
const editing = ref(false)
const form = ref({
	reference: '',
	title: '',
	type: 'grey_box' as TestType,
	exposure: 'internal' as TestExposure,
	planned_start: '',
	planned_end: '',
})
const transition = ref({ status: '' as TestStatus | '', comment: '' })
const busy = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

/** Statuts vers lesquels le test peut passer : tous sauf le statut courant. */
const targets = computed(() => TEST_STATUSES.filter((status) => status !== test.value?.status))

/**
 * Date de l'API affichée dans la langue courante.
 *
 * @param iso Date ISO 8601 en UTC, ou null.
 * @returns La date formatée, ou « non renseigné ».
 */
function display(iso: string | null): string {
	return (iso === null ? null : formatDateTime(iso, locale.value)) ?? t('common.none')
}

/**
 * Ligne de l'historique : date, transition (ou création), auteur et commentaire.
 *
 * @param change Entrée de l'historique des statuts.
 * @returns La phrase à afficher, entièrement traduite.
 */
function historyLine(change: StatusChange): string {
	const to = t(`tests.statuses.${change.to}`)
	const line = t('tests.historyEntry', {
		date: display(change.changed_at),
		change: change.from ? t('tests.historyChange', { from: t(`tests.statuses.${change.from}`), to }) : t('tests.historyCreated', { to }),
		author: change.author ?? t('tests.unknownAuthor'),
	})
	return change.comment ? t('tests.historyComment', { line, comment: change.comment }) : line
}

/**
 * Charge la fiche ; un test étranger ou inconnu donne « introuvable ».
 *
 * @param id Identifiant du test.
 */
async function load(id: string): Promise<void> {
	// Changement de fiche : rien de la précédente ne doit subsister (formulaire
	// ouvert, messages, saisie de statut).
	test.value = null
	missing.value = false
	editing.value = false
	message.value = null
	error.value = null
	transition.value = { status: '', comment: '' }
	try {
		const received = (await getJson<{ data: PenetrationTest }>(`/tests/${encodeURIComponent(id)}`)).data
		// Réponse d'une fiche quittée entre-temps : ignorée.
		if (id === props.id) {
			test.value = received
		}
	} catch (caught) {
		if (id !== props.id) {
			return
		}
		missing.value = caught instanceof ApiError && caught.status === 404
		error.value = missing.value ? null : t('auth.unexpected')
	}
}

/** Ouvre le formulaire, prérempli avec la fiche courante. */
function edit(): void {
	if (test.value) {
		form.value = {
			reference: test.value.reference,
			title: test.value.title,
			type: test.value.type,
			exposure: test.value.exposure,
			planned_start: toLocalInput(test.value.planned_start),
			planned_end: toLocalInput(test.value.planned_end),
		}
		editing.value = true
		message.value = null
	}
}

/**
 * Exécute une requête d'écriture, puis affiche la fiche renvoyée et un message.
 *
 * @param request Requête qui renvoie la fiche à jour.
 * @param done Message à annoncer en cas de succès.
 */
async function write(request: () => Promise<{ data: PenetrationTest }>, done: string): Promise<void> {
	busy.value = true
	error.value = null
	message.value = null
	const id = props.id
	try {
		const received = (await request()).data
		if (id !== props.id) {
			return
		}
		test.value = received
		editing.value = false
		message.value = done
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
	} finally {
		busy.value = false
	}
}

/** Enregistre la fiche ; les dates sont converties en UTC. */
async function save(): Promise<void> {
	await write(() => patchJson<{ data: PenetrationTest }>(`/tests/${encodeURIComponent(props.id)}`, {
		...form.value,
		planned_start: fromLocalInput(form.value.planned_start),
		planned_end: fromLocalInput(form.value.planned_end),
	}), t('common.saved'))
}

/** Change le statut ; le changement entre dans l'historique. */
async function changeStatus(): Promise<void> {
	await write(() => postJson<{ data: PenetrationTest }>(`/tests/${encodeURIComponent(props.id)}/status`, {
		status: transition.value.status,
		comment: transition.value.comment || null,
	}), t('tests.statusChanged'))
	// La saisie n'est vidée qu'après un changement réussi : en cas d'erreur, elle
	// reste à corriger.
	if (!error.value) {
		transition.value = { status: '', comment: '' }
	}
}

/** Supprime le test après confirmation, puis revient à la liste. */
async function remove(): Promise<void> {
	if (!test.value || !globalThis.confirm(t('tests.confirmDelete', { reference: test.value.reference }))) {
		return
	}
	busy.value = true
	error.value = null
	try {
		await deleteJson(`/tests/${encodeURIComponent(props.id)}`)
		await router.push({ name: 'tests' })
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
		{{ t('tests.notFound') }}
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
	<template v-if="test">
		<h2>{{ test.reference }} — {{ test.title }}</h2>
		<form
			v-if="editing"
			@submit.prevent="save"
		>
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
				<dt>{{ t('tests.organisation') }}</dt>
				<dd>
					<RouterLink :to="{ name: 'organisation', params: { id: test.tenant.id } }">
						{{ test.tenant.name }}
					</RouterLink>
				</dd>
				<dt>{{ t('tests.type') }}</dt>
				<dd>{{ t(`tests.types.${test.type}`) }}</dd>
				<dt>{{ t('tests.exposure') }}</dt>
				<dd>{{ t(`tests.exposures.${test.exposure}`) }}</dd>
				<dt>{{ t('tests.status') }}</dt>
				<dd>{{ t(`tests.statuses.${test.status}`) }}</dd>
				<dt>{{ t('tests.plannedStart') }}</dt>
				<dd>{{ display(test.planned_start) }}</dd>
				<dt>{{ t('tests.plannedEnd') }}</dt>
				<dd>{{ display(test.planned_end) }}</dd>
				<dt>{{ t('tests.createdBy') }}</dt>
				<dd>{{ test.created_by ?? t('common.none') }}</dd>
			</dl>
			<button
				v-if="test.can_manage"
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
				/>{{ t('tests.delete') }}
			</button>
		</template>

		<form
			v-if="test.can_manage"
			@submit.prevent="changeStatus"
		>
			<h3>{{ t('tests.changeStatus') }}</h3>
			<label for="test-nouveau-statut">{{ t('tests.newStatus') }}</label>
			<select
				id="test-nouveau-statut"
				v-model="transition.status"
				required
			>
				<option
					value=""
					disabled
				>
					{{ t('tests.chooseStatus') }}
				</option>
				<option
					v-for="status in targets"
					:key="status"
					:value="status"
				>
					{{ t(`tests.statuses.${status}`) }}
				</option>
			</select>
			<label for="test-commentaire">{{ t('tests.comment') }}</label>
			<textarea
				id="test-commentaire"
				v-model="transition.comment"
				maxlength="2000"
			/>
			<button
				type="submit"
				:disabled="busy"
			>
				<ActionIcon
					name="status"
					:busy="busy"
				/>{{ t('tests.applyStatus') }}
			</button>
		</form>

		<p>
			<RouterLink :to="{ name: 'test-scope', params: { id: test.id } }">
				{{ t('tests.scopeLink') }}
			</RouterLink>
		</p>
		<p>
			<RouterLink :to="{ name: 'test-vulnerabilities', params: { id: test.id } }">
				{{ t('tests.vulnerabilitiesLink') }}
			</RouterLink>
		</p>

		<RiskSummary :test-id="test.id" />

		<h3>{{ t('tests.phases') }}</h3>
		<ol>
			<li
				v-for="phase in test.phases"
				:key="phase.id"
			>
				{{ t(`tests.phaseNames.${phase.code}`) }}
			</li>
		</ol>

		<h3>{{ t('tests.history') }}</h3>
		<ul>
			<li
				v-for="(change, index) in test.status_changes"
				:key="index"
			>
				{{ historyLine(change) }}
			</li>
		</ul>
	</template>
</template>
