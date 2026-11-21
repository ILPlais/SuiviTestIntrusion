// L'API échange les dates en UTC (ISO 8601, suffixe Z ou décalage explicite) ;
// la SPA les affiche selon la langue, dans le fuseau du navigateur (13.07).
const ISO_WITH_ZONE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/

export function formatDateTime(iso: string, locale: string, timeZone?: string): string | null {
  if (!ISO_WITH_ZONE.test(iso)) return null
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return null
  return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeStyle: 'medium', timeZone }).format(date)
}
