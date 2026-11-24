<!--
	Composant racine : en-tête (titre, indicateur des requêtes en cours, compte
	connecté, déconnexion), écran de la route courante et pied de page avec l'état
	de l'instance, visible même sans être connecté.

	Quand une requête vers l'API dure plus de 300 ms, l'en-tête montre un
	throbber, hors du titre (socle-8). Pas d'aria-busy sur la zone principale :
	certains lecteurs d'écran y taisent alors les alertes d'erreur. Les icônes
	de la navigation sont décoratives, le texte des liens porte leur sens.
-->
<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { activeRequests } from './api/activity'
import HealthStatus from './components/HealthStatus.vue'
import NfIcon from './components/NfIcon.vue'
import LoadingThrobber from './components/LoadingThrobber.vue'
import { useAuthStore } from './stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

/** Délai avant d'afficher le throbber : une requête rapide ne fait rien clignoter. */
const THROBBER_DELAY_MS = 300

/** Vrai quand des requêtes sont en cours depuis plus de THROBBER_DELAY_MS. */
const working = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

watch(() => activeRequests.value > 0, (pending) => {
	clearTimeout(timer)
	if (pending) {
		timer = setTimeout(() => { working.value = true }, THROBBER_DELAY_MS)
	} else {
		working.value = false
	}
})

/** Ferme la session puis revient à l'écran de connexion. */
async function logout(): Promise<void> {
	await auth.logout()
	await router.push({ name: 'login' })
}
</script>

<template>
	<header box-="round">
		<div class="masthead">
			<h1>{{ t('app.title') }}</h1>
			<!-- Emplacement réservé : l'apparition du throbber ne décale pas l'en-tête. -->
			<span class="activity">
				<LoadingThrobber
					v-if="working"
					:label="t('common.working')"
					visible-label
				/>
			</span>
		</div>
		<nav
			v-if="auth.user"
			:aria-label="t('auth.accountMenu')"
		>
			<RouterLink :to="{ name: 'tests' }">
				<NfIcon name="tests" />{{ t('nav.tests') }}
			</RouterLink>
			<RouterLink :to="{ name: 'organisations' }">
				<NfIcon name="organisations" />{{ t('nav.organisations') }}
			</RouterLink>
			<RouterLink :to="{ name: 'company' }">
				<NfIcon name="company" />{{ t('nav.company') }}
			</RouterLink>
			<!-- Le compte à droite, comme sur les maquettes de 15.01 (socle-9). -->
			<span
				class="nav-spacer"
				aria-hidden="true"
			/>
			<RouterLink :to="{ name: 'profile' }">
				<NfIcon name="profile" />{{ auth.user.name }}
			</RouterLink>
			<RouterLink :to="{ name: 'security' }">
				<NfIcon name="security" />{{ t('auth.security') }}
			</RouterLink>
			<button
				type="button"
				@click="logout"
			>
				<NfIcon name="logout" />{{ t('auth.logout') }}
			</button>
		</nav>
	</header>
	<main>
		<RouterView />
	</main>
	<footer>
		<HealthStatus />
	</footer>
</template>
