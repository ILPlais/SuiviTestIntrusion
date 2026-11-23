/*
 * Gabarit commun des maquettes (15.01) : pose le thème, puis entoure le
 * <main> de chaque page de l'en-tête de l'application, du menu du test et du
 * pied de page, et remplace les icônes nommées par leur glyphe Nerd Fonts.
 *
 * Paramètres lus sur <body> :
 * - data-nav : entrée active de la navigation principale (tests, organisations…) ;
 * - data-section : entrée active du menu du test ; absent hors d'un test ;
 * - data-ecran : numéro de l'écran, rappelé dans le pied de page ;
 * - data-role : « client » pour la navigation réduite du client final.
 *
 * Thème : ?theme=clair ou ?theme=sombre dans l'adresse, sinon la préférence du
 * système, comme la SPA (src/theme.ts). Le gabarit est dupliqué ici, et non
 * importé de frontend/, pour que les maquettes s'ouvrent sans serveur.
 */

/**
 * Glyphes Nerd Fonts 3 (Material Design, nf-md-*) : nom d'usage → code hexadécimal.
 * Reprend src/icons/glyphs.json et y ajoute les icônes des écrans à venir.
 *
 * @type {Record<string, string>}
 */
const GLYPHS = {
	tests: 'f13da', organisations: 'f01d7', company: 'f00d6', profile: 'f0004',
	security: 'f099d', logout: 'f0343', create: 'f0415', edit: 'f03eb',
	save: 'f0193', cancel: 'f0156', delete: 'f01b4', status: 'f04e1',
	key: 'f0306', recovery: 'f087e', refresh: 'f0450', success: 'f05e0',
	warning: 'f0026', failure: 'f0159', error: 'f0028', check: 'f012c',
	planned: 'f00ed', in_progress: 'f0996', done: 'f023c', validated: 'f014e',
	history: 'f02da', info: 'f02fc', home: 'f02dc', phases: 'f027b',
	preview: 'f0208', bell: 'f009a', lock: 'f033e', unlock: 'f0fc6',
	team: 'f0849', admin: 'f0493', document: 'f0219', folder: 'f024b',
	download: 'f01da', upload: 'f0552', results: 'f0128', dashboard: 'f056e',
	target: 'f04fe', console: 'f018d', bug: 'f00e4', shield: 'f0565',
	export: 'f0207', engagement: 'f1218', scope: 'f0317', tasks: 'f0756',
	comment: 'f0182', invite: 'f0014', link: 'f0337', search: 'f0349',
	filter: 'f0232', milestone: 'f00f0', up: 'f005d', down: 'f0045',
	copy: 'f018f', share: 'f0497', flag: 'f023b', disable: 'f0013',
	image: 'f02e9', clock: 'f0150', sort: 'f04ba', cloud: 'f015f',
	web: 'f059f', server: 'f048b', calculator: 'f00ec', send: 'f048a',
	play: 'f040a', pause: 'f03e4',
}

/**
 * Entrées de la navigation principale : [clé de data-nav, icône, libellé].
 *
 * @type {Array<[string, string, string]>}
 */
const NAV = [
	['tests', 'tests', 'Tests'],
	['organisations', 'organisations', 'Organisations'],
	['company', 'company', 'Entreprise'],
	['admin', 'admin', 'Administration'],
]

/**
 * Menu d'un test : [clé de data-section, icône, libellé, compteur] ; une
 * entrée sans icône est un intertitre.
 *
 * @type {Array<[string, string, string, string]>}
 */
const TEST_MENU = [
	['synthese', 'dashboard', 'Synthèse', ''],
	['', '', 'Préparer', ''],
	['engagement', 'engagement', 'Engagement', '31/38'],
	['perimetre', 'scope', 'Périmètre', '14'],
	['', '', 'Tester', ''],
	['deroulement', 'tasks', 'Déroulement', '18/42'],
	['executions', 'console', "Exécutions d'outils", '23'],
	['vulnerabilites', 'bug', 'Vulnérabilités', '9'],
	['documents', 'folder', 'Documents', '31'],
	['', '', 'Restituer', ''],
	['resultats', 'results', 'Résultats', ''],
	['export', 'export', 'Exporter', ''],
	['', '', 'Suivre', ''],
	['equipe', 'team', 'Équipe et partage', '4'],
	['historique', 'history', 'Historique', ''],
]

/**
 * Choisit le thème d'après l'adresse ou la préférence du système.
 *
 * @returns {'clair' | 'sombre'} Thème à appliquer.
 */
function chooseTheme() {
	const requested = new URLSearchParams(location.search).get('theme')
	if (requested === 'clair' || requested === 'sombre') {
		return requested
	}
	return matchMedia('(prefers-color-scheme: dark)').matches ? 'sombre' : 'clair'
}

/**
 * Construit le code HTML d'une icône décorative.
 *
 * @param {string} name Nom d'usage du glyphe (clé de GLYPHS).
 * @returns {string} Élément <i> prêt à insérer.
 */
function icon(name) {
	return `<i class="i" data-i="${name}"></i>`
}

/**
 * Construit l'en-tête : titre, activité, état du coffre, navigation.
 *
 * @param {string} active Entrée active de la navigation principale.
 * @param {boolean} client Vrai pour le client final : ni organisations, ni entreprise, ni administration.
 * @returns {HTMLElement} En-tête de l'application.
 */
function buildHeader(active, client) {
	const header = document.createElement('header')
	header.className = 'app box'
	const user = client ? 'Dominique Bernard' : 'Camille Martin'
	const links = NAV.filter(([key]) => !client || key === 'tests').map(([key, glyph, label]) =>
		`<a href="#"${key === active ? ' aria-current="page"' : ''}>${icon(glyph)}${label}</a>`,
	).join('')
	header.innerHTML = `
		<div class="masthead">
			<h1>Suivi des tests d'intrusion</h1>
			<span class="spacer"></span>
			<span title="Clé privée déchiffrée dans le Worker crypto">${icon('unlock')} Coffre déverrouillé</span>
			<button type="button" class="secondary small">${icon('lock')}Verrouiller</button>
		</div>
		<nav aria-label="Navigation principale">
			${links}
			<span class="spacer"></span>
			<a href="#"${active === 'notifications' ? ' aria-current="page"' : ''}>${icon('bell')}Notifications <span class="counter">3</span></a>
			<a href="#">${icon('profile')}${user}</a>
			<a href="#">${icon('security')}Sécurité</a>
			<button type="button">${icon('logout')}Se déconnecter</button>
		</nav>`
	return header
}

/**
 * Construit le menu latéral d'un test, avec le rappel du test en tête.
 *
 * @param {string} active Entrée active du menu.
 * @returns {HTMLElement} Menu du test.
 */
function buildTestMenu(active) {
	const aside = document.createElement('aside')
	aside.className = 'test-menu'
	const items = TEST_MENU.map(([key, glyph, label, count]) => {
		if (!glyph) {
			return `<li class="group">${label}</li>`
		}
		const current = key === active ? ' aria-current="page"' : ''
		return `<li><a href="#"${current}>${icon(glyph)}${label}<span class="count">${count}</span></a></li>`
	}).join('')
	aside.innerHTML = `
		<fieldset>
			<legend>PT-2026-1008</legend>
			<small>Banque Exemple<br>Test externe du site vitrine</small>
			<nav aria-label="Sections du test"><ol>${items}</ol></nav>
		</fieldset>`
	return aside
}

/**
 * Construit le bandeau d'un test : fil d'Ariane, titre, statut et risque.
 *
 * @returns {HTMLElement} Bandeau placé en tête du contenu.
 */
function buildTestBanner() {
	const banner = document.createElement('div')
	banner.innerHTML = `
		<p class="crumbs"><a href="#">Tests</a> › <a href="#">Banque Exemple</a> › PT-2026-1008</p>
		<div class="test-banner">
			<h2>PT-2026-1008 — Test externe du site vitrine</h2>
			<span class="badge b-blue">${icon('in_progress')} En cours</span>
			<span class="badge b-outline">Boîte grise · externe</span>
			<span class="badge b-red" title="Score de gravité technique : maximum des vulnérabilités ouvertes">Risque : 🔴 Critique 9,8 (v3.1)</span>
			<small>${icon('planned')} 1 → 5 déc. 2026</small>
		</div>`
	return banner
}

/**
 * Construit le pied de page : état de l'instance et numéro de l'écran.
 *
 * @param {string} screen Numéro de l'écran (data-ecran).
 * @returns {HTMLElement} Pied de page.
 */
function buildFooter(screen) {
	const footer = document.createElement('footer')
	footer.className = 'app'
	footer.innerHTML = `
		<span>${icon('success')} L'API répond et la base de données est accessible.</span>
		<span>Heure du serveur : 3 décembre 2026 à 14:32</span>
		<span class="spacer"></span>
		<span>Maquette 15.01 — écran ${screen}</span>`
	return footer
}

/**
 * Remplace chaque <i class="i" data-i="…"> par son glyphe, masqué aux lecteurs d'écran.
 *
 * @returns {void}
 */
function renderIcons() {
	for (const element of document.querySelectorAll('i.i[data-i]')) {
		const code = GLYPHS[element.dataset.i]
		element.dataset.g = code ? String.fromCodePoint(parseInt(code, 16)) : '?'
		element.setAttribute('aria-hidden', 'true')
	}
}

/**
 * Monte le gabarit autour du <main> de la page.
 *
 * @returns {void}
 */
function mountLayout() {
	const body = document.body
	const main = document.querySelector('main')
	const section = body.dataset.section
	const layout = document.createElement('div')
	layout.className = section ? 'layout' : 'layout single'
	main.replaceWith(layout)
	if (section) {
		layout.append(buildTestMenu(section))
		main.prepend(buildTestBanner())
	}
	layout.append(main)
	if (body.dataset.nav !== 'none') {
		body.prepend(buildHeader(body.dataset.nav ?? 'tests', body.dataset.role === 'client'))
	}
	body.append(buildFooter(body.dataset.ecran ?? ''))
	renderIcons()
}

document.documentElement.dataset.theme = chooseTheme()
document.addEventListener('DOMContentLoaded', mountLayout)
