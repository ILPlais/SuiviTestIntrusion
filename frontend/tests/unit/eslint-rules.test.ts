// @vitest-environment node
/**
 * Les règles ESLint de 13.04 sont bloquantes : chaque écriture HTML ou
 * évaluation de code interdite produit une erreur, sauf dans le module de rendu.
 * Le JSDoc de 21.01 l'est aussi.
 */
import { beforeAll, describe, expect, it } from 'vitest'
import { ESLint } from 'eslint'

const eslint = new ESLint({ cwd: process.cwd() })

/**
 * Analyse un extrait de code comme s'il se trouvait dans `filePath`.
 *
 * @param code Extrait de code.
 * @param filePath Chemin simulé du fichier.
 * @returns Les identifiants des règles en erreur.
 */
async function errors(code: string, filePath: string): Promise<string[]> {
	const [result] = await eslint.lintText(code, { filePath })
	return result.messages.filter((m) => m.severity === 2).map((m) => m.ruleId ?? 'fatal')
}

describe('règles ESLint de 13.04', () => {
	// Le premier passage charge les extensions et la configuration : il peut être long.
	beforeAll(async () => {
		await eslint.lintText('', { filePath: 'src/prechauffage.ts' })
	}, 120_000)

	it.each([
		['eval', 'eval("1")\n', 'no-eval'],
		['Function', 'new Function("return 1")\n', 'no-new-func'],
		['setTimeout avec une chaîne', 'setTimeout("alert(1)", 1)\n', 'no-implied-eval'],
		['innerHTML', 'document.body.innerHTML = "x"\n', 'no-restricted-syntax'],
		['outerHTML', 'document.body.outerHTML = "x"\n', 'no-restricted-syntax'],
		['insertAdjacentHTML', 'document.body.insertAdjacentHTML("beforeend", "x")\n', 'no-restricted-syntax'],
		['document.write', 'document.write("x")\n', 'no-restricted-syntax'],
	])('refuse %s hors du module de rendu', async (_name, code, rule) => {
		expect(await errors(code, 'src/exemple.ts')).toContain(rule)
	})

	it('autorise innerHTML dans le module de rendu', async () => {
		expect(await errors('document.body.innerHTML = "x"\n', 'src/render/exemple.ts')).not.toContain('no-restricted-syntax')
	})

	it('refuse v-html et le texte écrit en dur dans un gabarit', async () => {
		const sfc = '<template>\n\t<p v-html="x">Bonjour</p>\n</template>\n'
		const found = await errors(sfc, 'src/components/Exemple.vue')
		expect(found).toContain('vue/no-v-html')
		expect(found).toContain('vue/no-bare-strings-in-template')
	})

	it('refuse une indentation par espaces', async () => {
		expect(await errors('export function f() {\n  return 1\n}\n', 'src/exemple.ts')).toContain('@stylistic/indent')
	})
})

describe('JSDoc obligatoire (21.01)', () => {
	it('refuse une fonction sans JSDoc, déclarée ou affectée à une variable', async () => {
		expect(await errors('export function f(): number {\n\treturn 1\n}\n', 'src/exemple.ts')).toContain('jsdoc/require-jsdoc')
		expect(await errors('export const g = (): number => 1\n', 'src/exemple.ts')).toContain('jsdoc/require-jsdoc')
	})

	it('exige une description, chaque paramètre décrit et le retour', async () => {
		const found = await errors('/**\n * @param x\n */\nexport function f(x: number): number {\n\treturn x\n}\n', 'src/exemple.ts')
		expect(found).toEqual(expect.arrayContaining(['jsdoc/require-description', 'jsdoc/require-param-description', 'jsdoc/require-returns']))
	})

	it('exige les types en JavaScript, pas en TypeScript', async () => {
		const doc = '/**\n * Double un nombre.\n *\n * @param x Nombre.\n * @returns Le double.\n */\nexport function f(x) {\n\treturn 2 * x\n}\n'
		expect(await errors(doc, 'scripts/exemple.mjs')).toContain('jsdoc/require-param-type')
		expect(await errors(doc.replace('(x)', '(x: number): number'), 'src/exemple.ts')).toEqual([])
	})

	it('dispense les fonctions passées en argument', async () => {
		expect(await errors('export const doubles = [1, 2].map((n) => n * 2)\n', 'src/exemple.ts')).toEqual([])
	})
})
