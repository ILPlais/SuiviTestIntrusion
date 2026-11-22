/**
 * Réduit la police Symbols Nerd Font aux seules icônes de la SPA (socle-8).
 *
 * La police complète (2,6 Mo) réunit des glyphes sous des licences diverses,
 * dont certains sans licence (Font Logos) ; la SPA n'emploie que des glyphes
 * Material Design (nf-md-*, Apache-2.0), listés dans src/icons/glyphs.json. La
 * police réduite pèse quelques kilo-octets et reste dans la politique de
 * licences (13.07) sans exception.
 *
 * Usage, après tout ajout d'icône :
 *   1. télécharger NerdFontsSymbolsOnly.zip de la version 3.5.1 de Nerd Fonts
 *      (https://github.com/ryanoasis/nerd-fonts/releases/tag/v3.5.1) ;
 *   2. installer fontTools et brotli (`python3 -m venv venv && venv/bin/pip install fonttools brotli`) ;
 *   3. `node scripts/subset-nerd-font.mjs <SymbolsNerdFontMono-Regular.ttf> [chemin de pyftsubset]`.
 */
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const output = join(root, 'src', 'assets', 'fonts', 'SymbolsNerdFontMono-subset.woff2')

/**
 * Codes Unicode des icônes déclarées, au format attendu par pyftsubset.
 *
 * @param {string} file Chemin de glyphs.json.
 * @returns {string} Liste `U+F13DA,U+F01D7,…`.
 */
function unicodes(file) {
	const { glyphs } = JSON.parse(readFileSync(file, 'utf8'))
	return Object.values(glyphs).map(({ code }) => `U+${code.toUpperCase()}`).join(',')
}

const [source, pyftsubset = 'pyftsubset'] = process.argv.slice(2)
if (!source) {
	console.error('Usage : node scripts/subset-nerd-font.mjs <SymbolsNerdFontMono-Regular.ttf> [pyftsubset]')
	process.exit(1)
}

execFileSync(pyftsubset, [
	source,
	`--unicodes=${unicodes(join(root, 'src', 'icons', 'glyphs.json'))}`,
	'--flavor=woff2',
	'--layout-features=',
	'--no-hinting',
	'--desubroutinize',
	`--output-file=${output}`,
], { stdio: 'inherit' })

console.log(`Police réduite écrite dans ${output}`)
