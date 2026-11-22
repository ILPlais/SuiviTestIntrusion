/**
 * Configuration ESLint de la SPA.
 *
 * - Sécurité (13.04) : pas de v-html, pas d'évaluation de code, pas d'écriture
 *   HTML brute hors du module de rendu `src/render/`.
 * - Langue (13.07) : aucun texte en dur dans les gabarits, tout passe par vue-i18n.
 * - Style (21.01) : indentation par tabulations.
 */
import js from '@eslint/js'
import stylistic from '@stylistic/eslint-plugin'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'
import tseslint from 'typescript-eslint'

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
		// Seul le module de rendu manipule du HTML brut, après assainissement (13.04).
		files: ['src/render/**'],
		rules: { 'no-restricted-syntax': 'off' },
	},
)
