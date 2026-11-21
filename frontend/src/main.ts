/**
 * Point d'entrée de la SPA : monte l'application avec Pinia (état) et vue-i18n (textes).
 */
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { i18n } from './i18n'

createApp(App).use(createPinia()).use(i18n).mount('#app')

// Le titre de l'onglet est traduit lui aussi : index.html ne porte que le nom du produit.
document.title = i18n.global.t('app.title')
