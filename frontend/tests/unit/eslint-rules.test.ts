// @vitest-environment node
/**
 * Les règles ESLint de 13.04 sont bloquantes : chaque écriture HTML ou
 * évaluation de code interdite produit une erreur, sauf dans le module de rendu.
 */
import { beforeAll, describe, expect, it } from 'vitest'
import { ESLint } from 'eslint'

const eslint = new ESLint({ cwd: process.cwd() })

/**
 * Analyse un extrait de code comme s'il se trouvait dans `filePath`.
 *
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
