<!--
	Synthèse du risque d'un test (resultats-1, 11.12, maquette 02 de 15.01) :
	gravité technique (maximum CVSS des vulnérabilités ouvertes), score moyen
	pondéré par la priorité, profil d'impact business et compteurs. Les barres
	en caractères sont décoratives : chaque compteur est écrit en clair.
-->
<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getJson } from '../api/client'
import { textBar, type TestRisk } from '../api/risk'
import { IMPACT_AXES, PRIORITIES, REMEDIATION_STATUSES, SEVERITIES } from '../api/vulnerabilities'
import { formatScore } from '../utils/cvss31'

const props = defineProps<{
	/** Identifiant (ULID) du test. */
	testId: string
}>()

const { t, locale } = useI18n()

const risk = ref<TestRisk | null>(null)
const failed = ref(false)

/** Largeur des barres, en caractères. */
const BAR_WIDTH = 16

/** Niveaux affichés dans le compteur par sévérité, du plus grave au moins grave. */
const SEVERITY_ROWS = [...SEVERITIES].reverse()

/**
 * Plus grand compteur d'une série.
 *
 * @param counts Compteurs.
 * @returns Le maximum, ou 0.
 */
function maxOf(counts: Record<string, number>): number {
	return Math.max(0, ...Object.values(counts))
}

/**
 * Charge le risque du test.
 *
 * @param id Identifiant du test.
 */
async function load(id: string): Promise<void> {
	risk.value = null
	failed.value = false
	try {
		const received = (await getJson<{ data: TestRisk }>(`/tests/${encodeURIComponent(id)}/risk`)).data
		if (id === props.testId) {
			risk.value = received
		}
	} catch {
		if (id === props.testId) {
			failed.value = true
		}
	}
}

watch(() => props.testId, load, { immediate: true })
</script>

<template>
	<section aria-labelledby="synthese-risque">
		<h3 id="synthese-risque">
			{{ t('risk.heading') }}
		</h3>
		<p
			v-if="failed"
			role="alert"
		>
			{{ t('risk.unavailable') }}
		</p>
		<template v-if="risk">
			<dl>
				<dt>{{ t('risk.maxScore') }}</dt>
				<dd>
					<template v-if="risk.max_score !== null && risk.max_severity !== null">
						<strong>{{ t(`vulnerabilities.severities.${risk.max_severity}`) }} {{ formatScore(risk.max_score, locale) }}</strong>
						({{ t('vulnerabilities.cvssVersion', { version: risk.cvss_version }) }})
					</template>
					<template v-else>
						{{ t('risk.none') }}
					</template>
				</dd>
				<dt>{{ t('risk.weightedAverage') }}</dt>
				<dd>{{ risk.weighted_average !== null ? t('risk.informative', { score: formatScore(risk.weighted_average, locale) }) : t('risk.none') }}</dd>
				<dt>{{ t('risk.openCount') }}</dt>
				<dd>{{ t('risk.openOfTotal', { open: risk.open_count, total: risk.total_count }) }}</dd>
				<template
					v-for="axis in IMPACT_AXES"
					:key="axis"
				>
					<dt>{{ t('risk.impact', { axis: t(`vulnerabilities.axes.${axis}`) }) }}</dt>
					<dd>{{ t('vulnerabilities.level', { level: risk.business_profile[axis], label: t(`vulnerabilities.levels.${axis}.${risk.business_profile[axis]}`) }) }}</dd>
				</template>
			</dl>

			<table>
				<caption>{{ t('risk.bySeverity') }}</caption>
				<tbody>
					<tr
						v-for="severity in SEVERITY_ROWS"
						:key="severity"
					>
						<th scope="row">
							{{ t(`vulnerabilities.severities.${severity}`) }}
						</th>
						<td aria-hidden="true">
							{{ textBar(risk.by_severity[severity], maxOf(risk.by_severity), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_severity[severity], maxOf(risk.by_severity), BAR_WIDTH).empty }}</span>
						</td>
						<td>{{ risk.by_severity[severity] }}</td>
					</tr>
					<tr>
						<th scope="row">
							{{ t('vulnerabilities.unrated') }}
						</th>
						<td aria-hidden="true">
							{{ textBar(risk.by_severity.unrated, maxOf(risk.by_severity), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_severity.unrated, maxOf(risk.by_severity), BAR_WIDTH).empty }}</span>
						</td>
						<td>{{ risk.by_severity.unrated }}</td>
					</tr>
				</tbody>
			</table>

			<table>
				<caption>{{ t('risk.byPriority') }}</caption>
				<tbody>
					<tr
						v-for="priority in PRIORITIES"
						:key="priority"
					>
						<th scope="row">
							{{ t(`vulnerabilities.remediation.priorities.${priority}`) }}
						</th>
						<td aria-hidden="true">
							{{ textBar(risk.by_priority[priority], maxOf(risk.by_priority), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_priority[priority], maxOf(risk.by_priority), BAR_WIDTH).empty }}</span>
						</td>
						<td>{{ risk.by_priority[priority] }}</td>
					</tr>
				</tbody>
			</table>

			<table>
				<caption>{{ t('risk.byRemediationStatus') }}</caption>
				<tbody>
					<tr
						v-for="status in REMEDIATION_STATUSES"
						:key="status"
					>
						<th scope="row">
							{{ t(`vulnerabilities.remediation.statuses.${status}`) }}
						</th>
						<td aria-hidden="true">
							{{ textBar(risk.by_remediation_status[status], maxOf(risk.by_remediation_status), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_remediation_status[status], maxOf(risk.by_remediation_status), BAR_WIDTH).empty }}</span>
						</td>
						<td>{{ risk.by_remediation_status[status] }}</td>
					</tr>
				</tbody>
			</table>

			<table>
				<caption>{{ t('risk.byPhase') }}</caption>
				<tbody>
					<tr
						v-for="(count, phase) in risk.by_phase"
						:key="phase"
					>
						<th scope="row">
							{{ t(`tests.phaseNames.${phase}`) }}
						</th>
						<td aria-hidden="true">
							{{ textBar(count, maxOf(risk.by_phase), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(count, maxOf(risk.by_phase), BAR_WIDTH).empty }}</span>
						</td>
						<td>{{ count }}</td>
					</tr>
				</tbody>
			</table>
		</template>
	</section>
</template>
