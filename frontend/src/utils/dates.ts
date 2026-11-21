/**
 * Affichage des dates (13.07).
 *
 * L'API échange les dates en UTC, au format ISO 8601 ; la SPA les affiche
 * selon les conventions de la langue, dans le fuseau horaire du navigateur.
 */

/** Date ISO 8601 avec un fuseau explicite (`Z` ou décalage `±hh:mm`). */
const ISO_WITH_ZONE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/

/**
 * Formate une date reçue de l'API pour l'affichage.
 *
 * Une date sans fuseau est refusée : le navigateur la lirait comme une heure
 * locale, et l'heure affichée serait fausse sans que rien ne le signale.
 *
 * @param iso Date ISO 8601 avec fuseau (ex. `2026-11-21T15:30:00Z`).
 * @param locale Langue d'affichage (ex. `fr`).
 * @param timeZone Fuseau IANA ; par défaut, celui du navigateur.
 * @returns La date formatée, ou `null` si l'entrée est invalide.
 */
export function formatDateTime(iso: string, locale: string, timeZone?: string): string | null {
	if (!ISO_WITH_ZONE.test(iso)) return null
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) return null
	return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeStyle: 'medium', timeZone }).format(date)
}
