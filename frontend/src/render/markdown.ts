/**
 * Rendu du texte riche (engagement-1) : les présentations et autres champs riches
 * sont saisis en Markdown, stockés tels quels par l'API et rendus ici seulement.
 *
 * Le HTML produit par `marked` est toujours assaini par DOMPurify (13.04) : un
 * Markdown peut contenir du HTML brut, qui ne doit jamais s'exécuter. C'est le
 * seul module, avec MarkdownView.vue, autorisé à écrire du HTML dans la page.
 */
import { Marked } from 'marked'
import { sanitizeHtml, trustedHtml } from '../security/trustedTypes'

/** Analyseur dédié, pour ne pas dépendre de la configuration globale de `marked`. */
const parser = new Marked({ async: false, gfm: true, breaks: false })

/**
 * Convertit du Markdown en HTML assaini, sous forme de chaîne.
 *
 * @param source Texte Markdown saisi par un utilisateur.
 * @returns Le HTML assaini (chaîne vide pour un texte vide).
 */
export function markdownToHtml(source: string): string {
	if (source.trim() === '') {
		return ''
	}

	return sanitizeHtml(parser.parse(source) as string)
}

/**
 * Convertit du Markdown en HTML prêt à insérer : un TrustedHTML créé par la
 * politique `sti-render` là où Trusted Types existe.
 *
 * @param source Texte Markdown saisi par un utilisateur.
 * @returns Le HTML assaini et typé.
 */
export function markdownToTrustedHtml(source: string): unknown {
	return source.trim() === '' ? '' : trustedHtml(parser.parse(source) as string)
}
