/**
 * Icônes Nerd Fonts de la SPA (socle-8), déclarées dans glyphs.json.
 *
 * Les glyphes viennent de la police Symbols Nerd Font réduite à cette liste
 * (scripts/subset-nerd-font.mjs) ; un nom absent de glyphs.json est une erreur
 * de typage.
 */
import table from './glyphs.json'

/** Nom d'usage d'une icône. */
export type IconName = keyof typeof table.glyphs

/** Tous les noms d'icônes déclarés. */
export const ICON_NAMES = Object.keys(table.glyphs) as IconName[]

/**
 * Caractère de l'icône dans la police Symbols Nerd Font.
 *
 * @param name Nom d'usage de l'icône.
 * @returns Le caractère (zone d'usage privé d'Unicode).
 */
export function glyph(name: IconName): string {
	return String.fromCodePoint(Number.parseInt(table.glyphs[name].code, 16))
}
