/**
 * Contrôle des licences des dépendances livrées et fichier de mentions (13.07).
 *
 * Lit `backend/composer.lock` (section `packages`, hors outils de développement)
 * et `frontend/package-lock.json` (paquets sans le drapeau `dev`), vérifie
 * chaque licence contre `policy.json`, puis écrit `THIRD-PARTY-NOTICES.md`.
 *
 * Usage : `node scripts/licenses/licenses.mjs [--check-notices]`.
 * Avec `--check-notices`, échoue si le fichier de mentions n'est pas à jour.
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Liste les licences entre lesquelles un paquet laisse le choix.
 *
 * Composer donne un tableau (licences au choix) ; npm donne une expression
 * SPDX, dont seules les alternatives « OR » sont gérées : une expression
 * « AND » ou inconnue est gardée telle quelle et sera donc refusée.
 *
 * @param {string|string[]|undefined} license Licence déclarée par le paquet.
 * @returns {string[]} Licences possibles, sans parenthèses.
 */
export function alternatives(license) {
	if (Array.isArray(license)) return license;
	if (typeof license !== 'string' || license.trim() === '') return [];
	return license.replace(/[()]/g, '').split(/\s+OR\s+/).map((part) => part.trim());
}

/**
 * Applique la politique de licences à une liste de paquets.
 *
 * @param {{name: string, version: string, license: string[], source: string}[]} packages
 * @param {{allowed: string[], exceptions: Record<string, string>}} policy
 *   `exceptions` autorise une licence précise pour un paquet nommé (LGPL au cas par cas).
 * @returns {{accepted: object[], refused: object[]}}
 */
export function check(packages, policy) {
	const accepted = [];
	const refused = [];
	for (const pkg of packages) {
		const ok = pkg.license.some((license) => policy.allowed.includes(license)
			|| policy.exceptions[pkg.name] === license);
		(ok ? accepted : refused).push(pkg);
	}
	return { accepted, refused };
}

/**
 * Extrait les dépendances livrées d'un `composer.lock`.
 *
 * @param {{packages: object[]}} lock Contenu JSON du verrou Composer.
 * @returns {{name: string, version: string, license: string[], source: string}[]}
 */
export function composerPackages(lock) {
	return lock.packages.map((pkg) => ({
		name: pkg.name,
		version: pkg.version,
		license: alternatives(pkg.license),
		source: 'composer',
	}));
}

/**
 * Extrait les dépendances livrées d'un `package-lock.json` (format 3).
 *
 * Le verrou produit par npm 9 ne contient pas toujours la licence : elle est
 * alors lue dans le `package.json` installé, via `readManifest`.
 *
 * @param {{packages: Record<string, object>}} lock Contenu JSON du verrou npm.
 * @param {(dir: string) => object|undefined} readManifest Lit `<dir>/package.json`.
 * @returns {{name: string, version: string, license: string[], source: string}[]}
 */
export function npmPackages(lock, readManifest) {
	return Object.entries(lock.packages)
		.filter(([dir, pkg]) => dir !== '' && !pkg.dev)
		.map(([dir, pkg]) => ({
			name: dir.replace(/^.*node_modules\//, ''),
			version: pkg.version,
			license: alternatives(pkg.license ?? readManifest(dir)?.license),
			source: 'npm',
		}));
}

/**
 * Rédige le fichier de mentions des dépendances livrées.
 *
 * @param {object[]} packages Paquets acceptés.
 * @returns {string} Contenu Markdown.
 */
export function notices(packages) {
	const rows = [...packages]
		.sort((a, b) => a.source.localeCompare(b.source) || a.name.localeCompare(b.name))
		.map((pkg) => `| ${pkg.source} | ${pkg.name} | ${pkg.version} | ${pkg.license.join(' ou ')} |`);
	return [
		'# Mentions des dépendances',
		'',
		'Dépendances livrées avec SuiviTestIntrusion et leurs licences. Fichier généré par',
		'`node scripts/licenses/licenses.mjs` : ne pas le modifier à la main.',
		'',
		'| **Source** | **Paquet** | **Version** | **Licence** |',
		'| --- | --- | --- | --- |',
		...rows,
		'',
	].join('\n');
}

/** Point d'entrée en ligne de commande. */
function main() {
	const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
	const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'));
	const policy = readJson(join(root, 'scripts', 'licenses', 'policy.json'));
	const frontend = join(root, 'frontend');
	const readManifest = (dir) => {
		const path = join(frontend, dir, 'package.json');
		return existsSync(path) ? readJson(path) : undefined;
	};

	const packages = [
		...composerPackages(readJson(join(root, 'backend', 'composer.lock'))),
		...npmPackages(readJson(join(frontend, 'package-lock.json')), readManifest),
	];
	const { accepted, refused } = check(packages, policy);

	for (const pkg of refused) {
		console.error(`Licence refusée : ${pkg.source} ${pkg.name} ${pkg.version} (${pkg.license.join(', ') || 'aucune'})`);
	}

	const target = join(root, 'THIRD-PARTY-NOTICES.md');
	const content = notices(accepted);
	if (process.argv.includes('--check-notices')) {
		const current = existsSync(target) ? readFileSync(target, 'utf8') : '';
		if (current !== content) {
			console.error('THIRD-PARTY-NOTICES.md n\'est pas à jour : lancer node scripts/licenses/licenses.mjs');
			process.exitCode = 1;
		}
	} else {
		writeFileSync(target, content);
	}

	console.log(`${accepted.length} dépendances acceptées, ${refused.length} refusées.`);
	if (refused.length > 0) process.exitCode = 1;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main();
