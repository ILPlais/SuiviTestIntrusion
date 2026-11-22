/**
 * Rendu du texte riche (engagement-1) : les présentations et autres champs riches
 * sont saisis en Markdown, stockés tels quels par l'API et rendus ici seulement.
 *
 * Le HTML produit par `marked` est toujours assaini par DOMPurify (13.04) : un
 * Markdown peut contenir du HTML brut, qui ne doit jamais s'exécuter. La liste
 * blanche est plus stricte que celle du filet `default` : ni style (la CSP
 * autorise les styles en ligne), ni formulaire, ni SVG, ni identifiant (pas de
 * faux écran de connexion, pas de DOM clobbering), liens en http(s) et mailto
 * seulement. C'est le seul module, avec MarkdownView.vue, autorisé à écrire du
 * HTML dans la page.
 */
import DOMPurify, { type Config } from 'dompurify'
import { Marked } from 'marked'
import { trustedHtml } from '../security/trustedTypes'

/** Analyseur dédié, pour ne pas dépendre de la configuration globale de `marked`. */
const parser = new Marked({ async: false, gfm: true, breaks: false })

/** Éléments et attributs admis dans un texte riche. */
const MARKDOWN_PURIFY: Config = {
	ALLOWED_TAGS: ['p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'del', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
	ALLOWED_ATTR: ['href', 'title', 'align', 'start'],
	ALLOWED_URI_REGEXP: /^(?:https?:|mailto:)/i,
}

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

	return DOMPurify.sanitize(parser.parse(source) as string, MARKDOWN_PURIFY) as string
}

/**
 * Convertit du Markdown en HTML prêt à insérer : le HTML de markdownToHtml(),
 * typé par la politique Trusted Types `sti-render` là où elle existe.
 *
 * @param source Texte Markdown saisi par un utilisateur.
 * @returns Le HTML assaini et typé.
 */
export function markdownToTrustedHtml(source: string): unknown {
	const html = markdownToHtml(source)

	return html === '' ? '' : trustedHtml(html)
}
