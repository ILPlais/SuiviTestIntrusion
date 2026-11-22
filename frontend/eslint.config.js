/**
 * Configuration ESLint de la SPA.
 *
 * - Sécurité (13.04) : pas de v-html, pas d'évaluation de code, pas d'écriture
 *   HTML brute hors du module de rendu `src/render/`.
 * - Langue (13.07) : aucun texte en dur dans les gabarits, tout passe par vue-i18n.
 * - Style (21.01) : indentation par tabulations ; JSDoc sur chaque fonction et
 *   classe (sans types en TypeScript, qui les porte déjà ; avec types en JavaScript).
 */
import js from '@eslint/js'
import stylistic from '@stylistic/eslint-plugin'
import pluginVue from 'eslint-plugin-vue'
import jsdoc from 'eslint-plugin-jsdoc'
import globals from 'globals'
import tseslint from 'typescript-eslint'

/**
 * Fonctions qui doivent porter un JSDoc (21.01) : déclarations, méthodes, classes et
 * fonctions affectées à une variable ; les fonctions passées en argument (rappels,
 * describe/it des tests) se lisent dans leur contexte.
 */
const jsdocRequired = {
	require: { FunctionDeclaration: true, MethodDefinition: true, ClassDeclaration: true },
	contexts: ['VariableDeclarator > ArrowFunctionExpression', 'VariableDeclarator > FunctionExpression'],
	publicOnly: false,
}

/** Règles JSDoc communes : description partout, ligne vide avant les balises. */
const jsdocRules = {
	'jsdoc/require-jsdoc': ['error', jsdocRequired],
	'jsdoc/require-description': 'error',
	'jsdoc/tag-lines': ['error', 'never', { startLines: 1 }],
}

/** Écritures HTML brutes réservées au module de rendu (13.04). */
const rawHtmlWrites = [
	{
		selector: "AssignmentExpression[left.type='MemberExpression'][left.property.name=/^(innerHTML|outerHTML)$/]",
		message: 'innerHTML et outerHTML ne s\'écrivent que dans src/render/ (13.04).',
	},
	{
		selector: "CallExpression[callee.property.name='insertAdjacentHTML']",
		message: 'insertAdjacentHTML n\'est permis que dans src/render/ (13.04).',
	},
	{
		selector: "CallExpression[callee.object.name='document'][callee.property.name=/^(write|writeln)$/]",
		message: 'document.write est interdit (13.04).',
	},
]

export default tseslint.config(
	{ ignores: ['dist/', 'node_modules/', 'playwright-report/', 'test-results/', 'playwright-report-production/', 'public/swagger-ui/'] },
	js.configs.recommended,
	...tseslint.configs.recommended,
	...pluginVue.configs['flat/recommended'],
	{
		files: ['**/*.vue'],
		languageOptions: { parserOptions: { parser: tseslint.parser } },
	},
	{
		languageOptions: { globals: { ...globals.browser, ...globals.node } },
		plugins: { '@stylistic': stylistic },
		rules: {
			'vue/no-v-html': 'error',
			'no-eval': 'error',
			'no-implied-eval': 'error',
			'no-new-func': 'error',
			'no-restricted-syntax': ['error', ...rawHtmlWrites],
			'vue/no-bare-strings-in-template': 'error',
			'@stylistic/indent': ['error', 'tab'],
			'vue/html-indent': ['error', 'tab'],
		},
	},
	{
		// Dans un composant, l'indentation du script est contrôlée par la règle Vue.
		files: ['**/*.vue'],
		rules: {
			'@stylistic/indent': 'off',
			'vue/script-indent': ['error', 'tab', { baseIndent: 0, switchCase: 1 }],
		},
	},
	{
		// TypeScript porte les types : le JSDoc décrit, sans les répéter.
		files: ['**/*.ts', '**/*.vue'],
		...jsdoc.configs['flat/recommended-typescript-error'],
		rules: { ...jsdoc.configs['flat/recommended-typescript-error'].rules, ...jsdocRules },
	},
	{
		// JavaScript : le JSDoc précise aussi les types.
		files: ['**/*.js', '**/*.mjs'],
		...jsdoc.configs['flat/recommended-error'],
		rules: { ...jsdoc.configs['flat/recommended-error'].rules, ...jsdocRules },
	},
	{
		// Seul le module de rendu manipule du HTML brut, après assainissement (13.04).
		files: ['src/render/**'],
		rules: { 'no-restricted-syntax': 'off' },
	},
)
