/**
 * Réduit la police Symbols Nerd Font aux seules icônes de la SPA (socle-8).
 *
 * La police complète pèse 2,6 Mo ; la SPA n'en embarque que les glyphes listés
 * dans src/icons/glyphs.json, de tout jeu (décision du 2026-11-23, 13.07 :
 * toutes les licences acceptées, y compris Font Logos, sans licence ; textes dans
 * src/assets/fonts/LICENSE.md). La police réduite pèse quelques kilo-octets. Un
 * code absent de la police source arrête le script : sans cela, l'icône
 * resterait vide sans que rien ne le signale.
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

// Contrôle préalable : chaque code demandé existe dans la police source.
const python = pyftsubset.replace(/pyftsubset$/, 'python3')
const missing = execFileSync(python, ['-c', [
	'import sys',
	'from fontTools.ttLib import TTFont',
	'cmap = TTFont(sys.argv[1]).getBestCmap()',
	'print(",".join(c for c in sys.argv[2].split(",") if int(c[2:], 16) not in cmap))',
].join('\n'), source, unicodes(join(root, 'src', 'icons', 'glyphs.json'))], { encoding: 'utf8' }).trim()
if (missing) {
	console.error(`Glyphes absents de la police source : ${missing}`)
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
