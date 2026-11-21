<!--
	Composant racine : en-tête (titre, compte connecté, déconnexion), écran de la
	route courante et pied de page avec l'état de l'instance, visible même sans
	être connecté.
-->
<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import HealthStatus from './components/HealthStatus.vue'
import { useAuthStore } from './stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

/** Ferme la session puis revient à l'écran de connexion. */
async function logout(): Promise<void> {
	await auth.logout()
	await router.push({ name: 'login' })
}
</script>

<template>
	<header>
		<h1>{{ t('app.title') }}</h1>
		<nav
			v-if="auth.user"
			:aria-label="t('auth.accountMenu')"
		>
			<span>{{ auth.user.name }}</span>
			<button
				type="button"
				@click="logout"
			>
				{{ t('auth.logout') }}
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
