/*
 * Captures des maquettes (15.01) : ouvre chaque page NN-*.html de ce dossier
 * dans Chromium sans interface, en thème clair puis sombre, et enregistre une
 * image pleine page « 15.01 <titre> (clair|sombre).png » dans le dossier parent.
 *
 * Usage : node capturer.mjs [chemin de chrome.exe]
 * Par défaut, Chromium est cherché dans %LOCALAPPDATA%\Chromium\Application.
 *
 * Pilote le protocole DevTools par le WebSocket intégré à Node (22 et plus) :
 * aucune dépendance npm, pour que les maquettes restent hors de frontend/.
 */
import { spawn } from 'node:child_process'
import { readdir, readFile, writeFile, mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

/** Dossier des sources des maquettes. */
const SOURCES = dirname(fileURLToPath(import.meta.url))

/** Largeur des captures, en pixels CSS. */
const WIDTH = 1440

/** Facteur d'échelle : 1,25 rend la police lisible une fois l'image réduite dans Obsidian. */
const SCALE = 1.25

/** Port du protocole DevTools. */
const PORT = 9333

/**
 * Attend une durée donnée.
 *
 * @param {number} ms Durée en millisecondes.
 * @returns {Promise<void>} Promesse résolue après la durée.
 */
function sleep(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms))
}

/**
 * Ouvre une session DevTools sur le premier onglet du navigateur lancé.
 *
 * @returns {Promise<(method: string, params?: object) => Promise<any>>} Fonction d'envoi des commandes.
 */
async function connect() {
	let targets = []
	for (let attempt = 0; attempt < 50 && targets.length === 0; attempt++) {
		try {
			const response = await fetch(`http://127.0.0.1:${PORT}/json/list`)
			targets = (await response.json()).filter((target) => target.type === 'page')
		} catch {
			await sleep(200)
		}
	}
	if (targets.length === 0) {
		throw new Error('Chromium ne répond pas sur le port DevTools.')
	}
	const socket = new WebSocket(targets[0].webSocketDebuggerUrl)
	await new Promise((resolve, reject) => {
		socket.onopen = resolve
		socket.onerror = reject
	})
	const pending = new Map()
	let nextId = 1
	socket.onmessage = (event) => {
		const message = JSON.parse(event.data)
		if (message.id && pending.has(message.id)) {
			const { resolve, reject } = pending.get(message.id)
			pending.delete(message.id)
			message.error ? reject(new Error(message.error.message)) : resolve(message.result)
		}
	}
	return (method, params = {}) => new Promise((resolve, reject) => {
		const id = nextId++
		pending.set(id, { resolve, reject })
		socket.send(JSON.stringify({ id, method, params }))
	})
}

/**
 * Extrait le titre d'une maquette depuis sa balise <title> (« 01 Liste des tests — maquette »).
 *
 * @param {string} file Nom du fichier HTML.
 * @returns {Promise<string>} Titre sans numéro ni suffixe, par exemple « Liste des tests ».
 */
async function screenTitle(file) {
	const html = await readFile(join(SOURCES, file), 'utf8')
	const title = /<title>\d+ (.+?) — maquette<\/title>/.exec(html)
	if (!title) {
		throw new Error(`Titre introuvable dans ${file}.`)
	}
	// Les deux-points sont interdits dans un nom de fichier Windows.
	return title[1].replace(/ ?: /g, ', ')
}

/**
 * Capture une page dans un thème, à la hauteur de son contenu.
 *
 * @param {(method: string, params?: object) => Promise<any>} send Envoi des commandes DevTools.
 * @param {string} file Nom du fichier HTML.
 * @param {'clair' | 'sombre'} theme Thème demandé.
 * @returns {Promise<Buffer>} Image PNG.
 */
async function capture(send, file, theme) {
	await send('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: 600, deviceScaleFactor: SCALE, mobile: false })
	const url = `${pathToFileURL(join(SOURCES, file)).href}?theme=${theme}`
	await send('Page.navigate', { url })
	await sleep(700)
	// Hauteur du contenu sans le min-height: 100vh du corps de page.
	const { result } = await send('Runtime.evaluate', {
		expression: `document.body.style.minHeight = 'auto'; document.fonts.ready.then(() => Math.ceil(document.documentElement.scrollHeight))`,
		awaitPromise: true,
		returnByValue: true,
	})
	const height = result.value
	await send('Emulation.setDeviceMetricsOverride', { width: WIDTH, height, deviceScaleFactor: SCALE, mobile: false })
	await send('Runtime.evaluate', { expression: `document.body.style.minHeight = ''` })
	await sleep(200)
	const { data } = await send('Page.captureScreenshot', { format: 'png' })
	return Buffer.from(data, 'base64')
}

const chrome = process.argv[2] ?? join(process.env.LOCALAPPDATA ?? '', 'Chromium', 'Application', 'chrome.exe')
const profile = await mkdtemp(join(tmpdir(), 'maquettes-'))
const browser = spawn(chrome, [
	'--headless=new',
	`--remote-debugging-port=${PORT}`,
	`--user-data-dir=${profile}`,
	'--hide-scrollbars',
	'--allow-file-access-from-files',
	'about:blank',
], { stdio: 'ignore' })

try {
	const send = await connect()
	await send('Page.enable')
	const files = (await readdir(SOURCES)).filter((name) => /^\d{2}-.+\.html$/.test(name)).sort()
	for (const file of files) {
		const title = await screenTitle(file)
		for (const theme of ['clair', 'sombre']) {
			const png = await capture(send, file, theme)
			const target = join(SOURCES, '..', `15.01 ${title} (${theme}).png`)
			await writeFile(target, png)
			console.log(`${file} (${theme}) → ${target}`)
		}
	}
} finally {
	browser.kill()
	await sleep(500)
	await rm(profile, { recursive: true, force: true }).catch(() => {})
}
