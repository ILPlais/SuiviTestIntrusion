<!--
	Gabarit des écrans d'un test (socle-9, maquettes 02 à 10 de 15.01) : menu
	latéral par étape (Synthèse ; Préparer ; Tester), fil d'Ariane et bandeau du
	test (référence et titre, statut, type et exposition, risque, dates), puis
	l'écran de la section courante.

	Le test est chargé une fois ici et fourni aux écrans (TEST_CONTEXT), qui le
	font recharger après une écriture. Les sections sans écran (engagement,
	exécutions, documents, résultats, export, équipe, historique) n'apparaissent
	qu'avec leurs stories.
-->
<script setup lang="ts">
import { computed, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError, getJson } from '../api/client'
import type { PenetrationTest } from '../api/penetrationTests'
import { severityTag, statusTag, TEST_CONTEXT } from '../api/testContext'
import LoadingThrobber from '../components/LoadingThrobber.vue'
import NfIcon from '../components/NfIcon.vue'
import { formatScore } from '../utils/cvss31'

const props = defineProps<{
	/** Identifiant (ULID) du test. */
	id: string
}>()

const { t, locale } = useI18n()
const route = useRoute()

const test = ref<PenetrationTest | null>(null)
const missing = ref(false)
const error = ref<string | null>(null)

/** Section du menu à marquer : une fiche de vulnérabilité relève de « Vulnérabilités ». */
const section = computed(() => {
	const name = String(route.name ?? '')
	return name === 'vulnerability' ? 'test-vulnerabilities' : name
})

/**
 * Date courte du bandeau (« 1 déc. 2026 »).
 *
 * @param iso Date ISO 8601, ou null.
 * @returns La date, ou null.
 */
function shortDate(iso: string | null): string | null {
	return iso === null ? null : new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}

/** Période prévue du test, ou null si aucune date n'est saisie. */
const period = computed(() => {
	const start = shortDate(test.value?.planned_start ?? null)
	const end = shortDate(test.value?.planned_end ?? null)
	return start || end ? `${start ?? '…'} → ${end ?? '…'}` : null
})

/**
 * Charge le test ; un test étranger ou inconnu donne « introuvable ».
 *
 * @param id Identifiant du test.
 */
async function load(id: string): Promise<void> {
	try {
		const received = (await getJson<{ data: PenetrationTest }>(`/tests/${encodeURIComponent(id)}`)).data
		if (id === props.id) {
			test.value = received
			missing.value = false
			error.value = null
		}
	} catch (caught) {
		if (id === props.id) {
			test.value = null
			missing.value = caught instanceof ApiError && caught.status === 404
			error.value = missing.value ? null : t('auth.unexpected')
		}
	}
}

provide(TEST_CONTEXT, { test, refresh: () => load(props.id) })

watch(() => props.id, (id) => {
	test.value = null
	void load(id)
}, { immediate: true })
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
	<LoadingThrobber
		v-if="!test && !missing && !error"
		:label="t('common.loading')"
		visible-label
	/>
	<div
		v-if="test"
		class="test-layout"
	>
		<aside class="test-menu">
			<section
				class="panel"
				aria-labelledby="menu-test-titre"
			>
				<h2
					id="menu-test-titre"
					class="panel-title"
				>
					{{ test.reference }}
				</h2>
				<p>
					<small>{{ test.tenant.name }}<br>{{ test.title }}</small>
				</p>
				<nav :aria-label="t('testMenu.label')">
					<ol>
						<li>
							<RouterLink
								:to="{ name: 'test', params: { id: test.id } }"
								:class="{ current: section === 'test' }"
							>
								<NfIcon name="dashboard" />{{ t('testMenu.synthesis') }}
							</RouterLink>
						</li>
						<li
							class="menu-group"
							aria-hidden="true"
						>
							{{ t('testMenu.prepare') }}
						</li>
						<li>
							<RouterLink
								:to="{ name: 'test-scope', params: { id: test.id } }"
								:class="{ current: section === 'test-scope' }"
							>
								<NfIcon name="scope" />{{ t('testMenu.scope') }}
							</RouterLink>
						</li>
						<li
							class="menu-group"
							aria-hidden="true"
						>
							{{ t('testMenu.test') }}
						</li>
						<li>
							<RouterLink
								:to="{ name: 'test-vulnerabilities', params: { id: test.id } }"
								:class="{ current: section === 'test-vulnerabilities' }"
								:aria-current="section === 'test-vulnerabilities' ? 'page' : undefined"
							>
								<NfIcon name="vulnerabilities" />{{ t('testMenu.vulnerabilities') }}
							</RouterLink>
						</li>
					</ol>
				</nav>
			</section>
		</aside>

		<div class="test-content">
			<p class="crumbs">
				<RouterLink :to="{ name: 'tests' }">
					{{ t('nav.tests') }}
				</RouterLink>
				{{ t('testMenu.crumbSeparator') }}
				<RouterLink :to="{ name: 'organisation', params: { id: test.tenant.id } }">
					{{ test.tenant.name }}
				</RouterLink>
				{{ t('testMenu.crumbSeparator') }} {{ test.reference }}
			</p>
			<div class="test-banner">
				<h2>{{ test.reference }} — {{ test.title }}</h2>
				<span
					class="tag"
					:class="statusTag(test.status)"
				>{{ t(`tests.statuses.${test.status}`) }}</span>
				<span class="tag tag-outline">{{ t(`tests.types.${test.type}`) }} · {{ t(`tests.exposures.${test.exposure}`) }}</span>
				<span
					class="tag"
					:class="severityTag(test.risk.max_severity)"
				>
					<template v-if="test.risk.max_score !== null && test.risk.max_severity !== null">
						{{ t('testMenu.risk', { severity: t(`vulnerabilities.severities.${test.risk.max_severity}`), score: formatScore(test.risk.max_score, locale) }) }}
					</template>
					<template v-else>
						{{ t('testMenu.noRisk') }}
					</template>
				</span>
				<small v-if="period">{{ period }}</small>
			</div>
			<RouterView />
		</div>
	</div>
</template>
