<!--
	Synthèse du risque d'un test (resultats-1, 11.12, maquette 02 de 15.01) :
	cadres « Gravité technique » (maximum CVSS des vulnérabilités ouvertes),
	« Score agrégé » (moyenne pondérée par la priorité) et « Profil d'impact
	business », puis compteurs par sévérité, priorité, statut de remédiation et
	phase. Les barres en caractères sont décoratives : chaque compteur est écrit.
-->
<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getJson } from '../api/client'
import { textBar, type TestRisk } from '../api/risk'
import { severityTag } from '../api/testContext'
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

/** Niveaux du compteur par sévérité, du plus grave au moins grave. */
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
	<p
		v-if="failed"
		role="alert"
	>
		{{ t('risk.unavailable') }}
	</p>
	<template v-if="risk">
		<div class="grid-3">
			<section
				class="panel"
				aria-labelledby="risque-gravite"
			>
				<h3
					id="risque-gravite"
					class="panel-title"
				>
					{{ t('risk.maxScore') }}
				</h3>
				<template v-if="risk.max_score !== null && risk.max_severity !== null">
					<p class="kpi-value">
						{{ formatScore(risk.max_score, locale) }}
					</p>
					<p>
						<span
							class="tag"
							:class="severityTag(risk.max_severity)"
						>{{ t(`vulnerabilities.severities.${risk.max_severity}`) }}</span>
						{{ t('vulnerabilities.cvssVersion', { version: risk.cvss_version }) }}
					</p>
				</template>
				<p v-else>
					{{ t('risk.none') }}
				</p>
				<p><small>{{ t('risk.openOfTotal', { open: risk.open_count, total: risk.total_count }) }}</small></p>
			</section>
			<section
				class="panel"
				aria-labelledby="risque-agrege"
			>
				<h3
					id="risque-agrege"
					class="panel-title"
				>
					{{ t('risk.weightedAverage') }}
				</h3>
				<template v-if="risk.weighted_average !== null">
					<p class="kpi-value">
						{{ formatScore(risk.weighted_average, locale) }}
					</p>
					<p><small>{{ t('risk.informativeNote') }}</small></p>
				</template>
				<p v-else>
					{{ t('risk.none') }}
				</p>
			</section>
			<section
				class="panel"
				aria-labelledby="risque-impact"
			>
				<h3
					id="risque-impact"
					class="panel-title"
				>
					{{ t('risk.businessProfile') }}
				</h3>
				<dl>
					<template
						v-for="axis in IMPACT_AXES"
						:key="axis"
					>
						<dt>{{ t(`vulnerabilities.axes.${axis}`) }}</dt>
						<dd>{{ t('vulnerabilities.level', { level: risk.business_profile[axis], label: t(`vulnerabilities.levels.${axis}.${risk.business_profile[axis]}`) }) }}</dd>
					</template>
				</dl>
			</section>
		</div>

		<div class="grid-3">
			<section
				class="panel"
				aria-labelledby="risque-severite"
			>
				<h3
					id="risque-severite"
					class="panel-title"
				>
					{{ t('risk.bySeverity') }}
				</h3>
				<div class="bars">
					<template
						v-for="severity in SEVERITY_ROWS"
						:key="severity"
					>
						<span>{{ t(`vulnerabilities.severities.${severity}`) }}</span>
						<span
							class="bar"
							aria-hidden="true"
						>{{ textBar(risk.by_severity[severity], maxOf(risk.by_severity), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_severity[severity], maxOf(risk.by_severity), BAR_WIDTH).empty }}</span></span>
						<span>{{ risk.by_severity[severity] }}</span>
					</template>
					<span>{{ t('vulnerabilities.unrated') }}</span>
					<span
						class="bar"
						aria-hidden="true"
					>{{ textBar(risk.by_severity.unrated, maxOf(risk.by_severity), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_severity.unrated, maxOf(risk.by_severity), BAR_WIDTH).empty }}</span></span>
					<span>{{ risk.by_severity.unrated }}</span>
				</div>
			</section>
			<section
				class="panel"
				aria-labelledby="risque-priorite"
			>
				<h3
					id="risque-priorite"
					class="panel-title"
				>
					{{ t('risk.byPriority') }}
				</h3>
				<div class="bars">
					<template
						v-for="priority in PRIORITIES"
						:key="priority"
					>
						<span>{{ priority }}</span>
						<span
							class="bar"
							aria-hidden="true"
						>{{ textBar(risk.by_priority[priority], maxOf(risk.by_priority), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_priority[priority], maxOf(risk.by_priority), BAR_WIDTH).empty }}</span></span>
						<span>{{ risk.by_priority[priority] }}</span>
					</template>
				</div>
			</section>
			<section
				class="panel"
				aria-labelledby="risque-remediation"
			>
				<h3
					id="risque-remediation"
					class="panel-title"
				>
					{{ t('risk.byRemediationStatus') }}
				</h3>
				<div class="bars">
					<template
						v-for="status in REMEDIATION_STATUSES"
						:key="status"
					>
						<span>{{ t(`vulnerabilities.remediation.statuses.${status}`) }}</span>
						<span
							class="bar"
							aria-hidden="true"
						>{{ textBar(risk.by_remediation_status[status], maxOf(risk.by_remediation_status), BAR_WIDTH).filled }}<span class="bar-empty">{{ textBar(risk.by_remediation_status[status], maxOf(risk.by_remediation_status), BAR_WIDTH).empty }}</span></span>
						<span>{{ risk.by_remediation_status[status] }}</span>
					</template>
				</div>
				<p><small>{{ t('risk.verifiedExcluded') }}</small></p>
			</section>
		</div>

		<section
			class="panel"
			aria-labelledby="risque-phases"
		>
			<h3
				id="risque-phases"
				class="panel-title"
			>
				{{ t('risk.byPhase') }}
			</h3>
			<div class="bars">
				<template
					v-for="(count, phase) in risk.by_phase"
					:key="phase"
				>
					<span>{{ t(`tests.phaseNames.${phase}`) }}</span>
					<span
						class="bar"
						aria-hidden="true"
					>{{ textBar(count, maxOf(risk.by_phase), BAR_WIDTH * 2).filled }}<span class="bar-empty">{{ textBar(count, maxOf(risk.by_phase), BAR_WIDTH * 2).empty }}</span></span>
					<span>{{ count }}</span>
				</template>
			</div>
		</section>
	</template>
</template>
