/**
 * Remet en forme les tableaux dérivés de .stories/epics et .stories/sprints après
 * une écriture du ledger : wikiliens Obsidian sur les identifiants, colonnes
 * alignées, ligne vide après le commentaire. Le tableau du ledger (collé au
 * commentaire) fait foi ; un ancien tableau mis en forme qui le suit est retiré.
 *
 * Usage : node format-stories-tables.js <racine du dépôt>
 */
const fs = require('fs')
const path = require('path')

const root = process.argv[2]
const MARKER = '<!-- Derived from story files'

/**
 * Découpe une ligne de tableau Markdown en cellules.
 *
 * @param {string} line Ligne `| a | b |`.
 * @returns {string[]} Cellules sans les espaces de bord.
 */
function cells(line) {
	return line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((cell) => cell.trim())
}

/**
 * Met en forme un tableau : wikiliens sur la première colonne, colonnes alignées.
 *
 * @param {string[][]} rows En-tête puis lignes (sans la ligne de séparation).
 * @returns {string[]} Lignes du tableau mis en forme.
 */
function format(rows) {
	const body = rows.slice(1).map((row) => [row[0].startsWith('[[') ? row[0] : `[[${row[0]}]]`, ...row.slice(1)])
	const all = [rows[0], ...body]
	const widths = rows[0].map((_, index) => Math.max(3, ...all.map((row) => [...row[index]].length)))
	const line = (row) => `| ${row.map((cell, index) => cell + ' '.repeat(widths[index] - [...cell].length)).join(' | ')} |`
	return [line(all[0]), `| ${widths.map((width) => '-'.repeat(width)).join(' | ')} |`, ...body.map(line)]
}

for (const folder of ['epics', 'sprints']) {
	const directory = path.join(root, '.stories', folder)
	for (const name of fs.readdirSync(directory).filter((file) => file.endsWith('.md'))) {
		const file = path.join(directory, name)
		const text = fs.readFileSync(file, 'utf8')
		const eol = text.includes('\r\n') ? '\r\n' : '\n'
		const lines = text.split(/\r?\n/)
		const start = lines.findIndex((line) => line.startsWith(MARKER))
		if (start < 0) continue

		// Tableaux qui suivent le commentaire : celui du ledger (collé) puis l'ancien.
		let index = start + 1
		const tables = []
		while (index < lines.length && (lines[index].trim() === '' || lines[index].trim().startsWith('|'))) {
			if (lines[index].trim().startsWith('|')) {
				const table = []
				while (index < lines.length && lines[index].trim().startsWith('|')) table.push(lines[index++])
				tables.push(table)
			} else {
				index++
			}
		}
		if (tables.length === 0) continue

		// Source : le tableau collé au commentaire s'il existe (écrit par le ledger), sinon le seul présent.
		const source = lines[start + 1].trim().startsWith('|') ? tables[0] : tables[tables.length - 1]
		const rows = source.map(cells).filter((row) => !row.every((cell) => /^-+$/.test(cell)))
		const output = [...lines.slice(0, start + 1), '', ...format(rows), '', ...lines.slice(index)]
		const next = output.join(eol)
		if (next !== text) {
			fs.writeFileSync(file, next)
			console.log(`mis en forme : ${folder}/${name}`)
		}
	}
}
