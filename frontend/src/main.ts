/**
 * Point d'entrée de la SPA : monte l'application avec Pinia (état), vue-i18n
 * (textes) et Vue Router (écrans).
 */
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { i18n } from './i18n'
import { createAppRouter } from './router'
import { installTrustedTypesPolicies } from './security/trustedTypes'

// Politiques Trusted Types avant tout rendu : la CSP refuse toute chaîne brute (13.04).
installTrustedTypesPolicies()

createApp(App).use(createPinia()).use(i18n).use(createAppRouter()).mount('#app')

// Le titre de l'onglet est traduit lui aussi : index.html ne porte que le nom du produit.
document.title = i18n.global.t('app.title')
