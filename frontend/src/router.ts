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
 */
export function createAppRouter(history: RouterHistory = createWebHistory()): Router {
	const router = createRouter({
		history,
		routes: [
			{ path: '/', name: 'home', component: () => import('./views/HomeView.vue'), meta: { requiresAuth: true } },
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

		return user ? true : { name: 'login', query: { redirect: to.fullPath } }
	})

	return router
}
