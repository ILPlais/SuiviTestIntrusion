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

/** Valeur d'un champ `datetime-local` : date et heure, sans fuseau. */
const LOCAL_INPUT = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/

/**
 * Nombre sur deux chiffres (mois, jour, heure, minute).
 *
 * @param value Nombre de 0 à 99.
 * @returns Le nombre, précédé d'un zéro s'il n'a qu'un chiffre.
 */
function pad(value: number): string {
	return String(value).padStart(2, '0')
}

/**
 * Valeur d'un champ `datetime-local` pour une date reçue de l'API, exprimée
 * dans le fuseau du navigateur.
 *
 * @param iso Date ISO 8601 avec fuseau, ou null.
 * @returns `AAAA-MM-JJTHH:MM` dans le fuseau du navigateur, ou une chaîne vide.
 */
export function toLocalInput(iso: string | null): string {
	if (iso === null || !ISO_WITH_ZONE.test(iso)) return ''
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) return ''
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/**
 * Date à envoyer à l'API pour la valeur d'un champ `datetime-local` : le champ
 * est lu dans le fuseau du navigateur, puis converti en UTC (13.07).
 *
 * @param local Valeur du champ (`AAAA-MM-JJTHH:MM`), ou chaîne vide.
 * @returns La date ISO 8601 en UTC, ou null si le champ est vide ou invalide.
 */
export function fromLocalInput(local: string): string | null {
	if (!LOCAL_INPUT.test(local)) return null
	// Sans fuseau, le constructeur lit une date-heure comme une heure locale.
	const date = new Date(local)
	return Number.isNaN(date.getTime()) ? null : date.toISOString()
}
