/**
 * Thème WebTUI de la SPA (socle-8) : Catppuccin Mocha (sombre) ou Latte
 * (clair), selon la préférence du système, suivie en direct.
 *
 * Le thème est posé par l'attribut `data-webtui-theme` de la racine, depuis ce
 * module et non par un script en ligne, que la CSP refuse (13.04).
 */

/** Thèmes Catppuccin employés (paquet `@webtui/theme-catppuccin`). */
export type ThemeName = 'catppuccin-mocha' | 'catppuccin-latte'

/**
 * Thème correspondant à la préférence du système.
 *
 * @param prefersDark Vrai si le système demande un thème sombre.
 * @returns Le nom du thème WebTUI.
 */
export function themeFor(prefersDark: boolean): ThemeName {
	return prefersDark ? 'catppuccin-mocha' : 'catppuccin-latte'
}

/**
 * Pose le thème sur la racine, puis le met à jour quand la préférence change.
 *
 * @param root Élément racine (`document.documentElement`).
 * @param media Requête média `(prefers-color-scheme: dark)`.
 */
export function installTheme(root: HTMLElement, media: MediaQueryList): void {
	root.setAttribute('data-webtui-theme', themeFor(media.matches))
	media.addEventListener('change', (event) => root.setAttribute('data-webtui-theme', themeFor(event.matches)))
}
