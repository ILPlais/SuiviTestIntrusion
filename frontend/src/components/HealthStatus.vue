<!--
	État de l'instance : message d'état, heure du serveur et bouton de nouvelle vérification.
	Le message est dans une zone `role="status"`, annoncée par les lecteurs d'écran (13.07).
-->
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import type { IconName } from '../icons'
import { useHealthStore } from '../stores/health'
import NfIcon from './NfIcon.vue'
import { formatDateTime } from '../utils/dates'

const { t, locale } = useI18n()
const health = useHealthStore()

const message = computed(() => {
	switch (health.state) {
		case 'ok': return t('health.ok')
		case 'degraded': return t('health.degraded')
		case 'unreachable': return t('health.unreachable')
		default: return t('health.checking')
	}
})

/** Icône de l'état : succès, avertissement ou échec (le texte porte le sens). */
const icon = computed<IconName>(() => {
	switch (health.state) {
		case 'ok': return 'success'
		case 'degraded': return 'warning'
		default: return 'failure'
	}
})

const serverTime = computed(() =>
	health.serverTime ? formatDateTime(health.serverTime, locale.value) : null,
)

onMounted(() => health.check())
</script>

<template>
	<section aria-labelledby="health-heading">
		<h2 id="health-heading">
			{{ t('health.heading') }}
		</h2>
		<p
			role="status"
			:data-state="health.state"
		>
			<!-- Spinner seul pendant la vérification : le message dit déjà ce qui se passe. -->
			<span
				v-if="health.state === 'checking'"
				is-="spinner"
				variant-="dots"
				aria-hidden="true"
			/>
			<NfIcon
				v-else
				:name="icon"
			/>
			{{ message }}
		</p>
		<p v-if="serverTime">
			{{ t('health.serverTime', { time: serverTime }) }}
		</p>
		<button
			type="button"
			:disabled="health.state === 'checking'"
			@click="health.check()"
		>
			<NfIcon name="refresh" />{{ t('health.retry') }}
		</button>
	</section>
</template>
