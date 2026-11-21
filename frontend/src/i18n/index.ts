import { createI18n } from 'vue-i18n'
import fr from './locales/fr.json'

// V1 en français seul ; toute chaîne affichée passe par ces messages (13.07).
export type MessageSchema = typeof fr

export const i18n = createI18n<[MessageSchema], 'fr'>({
  legacy: false,
  locale: 'fr',
  fallbackLocale: 'fr',
  messages: { fr },
})
