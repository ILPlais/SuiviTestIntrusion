/**
 * Traductions de l'interface (13.07).
 *
 * La V1 est en français seul, mais toute chaîne affichée passe par ces
 * messages : ajouter une langue (exploitation-3, V2) ne touchera pas au code.
 */
import { createI18n } from 'vue-i18n'
import fr from './locales/fr.json'

/** Structure des messages, déduite du fichier français (référence des clés). */
export type MessageSchema = typeof fr

/** Instance vue-i18n en mode Composition API (`useI18n`). */
export const i18n = createI18n<[MessageSchema], 'fr'>({
	legacy: false,
	locale: 'fr',
	fallbackLocale: 'fr',
	messages: { fr },
})
