/**
 * Routes de la SPA (identite-1). Les écrans sont chargés à la demande.
 *
 * Toute route marquée `requiresAuth` vérifie la session ; sans session, elle
 * renvoie vers la connexion, qui ramène ensuite à la page demandée.
 */
import { createRouter, createWebHistory, type Router, type RouterHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

/**
 * Crée le routeur ; l'historique est injectable pour les tests.
 *
 * @param history Historique de navigation (en mémoire dans les tests).
 * @returns Le routeur, avec la garde de session et de double authentification.
 */
export function createAppRouter(history: RouterHistory = createWebHistory()): Router {
	const router = createRouter({
		history,
		routes: [
			{ path: '/', name: 'home', component: () => import('./views/HomeView.vue'), meta: { requiresAuth: true } },
			{ path: '/securite', name: 'security', component: () => import('./views/SecurityView.vue'), meta: { requiresAuth: true } },
			{ path: '/profil', name: 'profile', component: () => import('./views/ProfileView.vue'), meta: { requiresAuth: true } },
			{ path: '/organisations', name: 'organisations', component: () => import('./views/OrganisationsView.vue'), meta: { requiresAuth: true } },
			{ path: '/organisations/:id', name: 'organisation', component: () => import('./views/OrganisationView.vue'), props: true, meta: { requiresAuth: true } },
			{ path: '/tests', name: 'tests', component: () => import('./views/TestsView.vue'), meta: { requiresAuth: true } },
			{ path: '/tests/:id', name: 'test', component: () => import('./views/TestView.vue'), props: true, meta: { requiresAuth: true } },
			{ path: '/entreprise', name: 'company', component: () => import('./views/CompanyView.vue'), meta: { requiresAuth: true } },
			{ path: '/connexion', name: 'login', component: () => import('./views/LoginView.vue') },
			{ path: '/activation/:token', name: 'activation', component: () => import('./views/ActivationView.vue'), props: true },
			{ path: '/:chemin(.*)*', redirect: { name: 'home' } },
		],
	})

	router.beforeEach(async (to) => {
		if (!to.meta.requiresAuth) {
			return true
		}

		const auth = useAuthStore()
		const user = auth.user === undefined ? await auth.fetchUser() : auth.user

		if (!user) {
			return { name: 'login', query: { redirect: to.fullPath } }
		}

		// Politique d'instance : sans second facteur, seul l'écran Sécurité est accessible (identite-3).
		return user.two_factor_setup_required && to.name !== 'security' ? { name: 'security' } : true
	})

	return router
}
