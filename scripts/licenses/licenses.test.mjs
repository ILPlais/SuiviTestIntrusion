/**
 * Tests du contrôle des licences (13.07) : `node --test scripts/licenses/`.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { alternatives, check, composerPackages, npmPackages } from './licenses.mjs';

const policy = { allowed: ['MIT', 'BSD-3-Clause', 'Apache-2.0'], exceptions: { 'openpgp': 'LGPL-3.0-or-later' } };

test('une expression SPDX « OR » donne le choix entre ses licences', () => {
	assert.deepEqual(alternatives('(MIT OR Apache-2.0)'), ['MIT', 'Apache-2.0']);
	assert.deepEqual(alternatives(['BSD-3-Clause', 'GPL-2.0-only']), ['BSD-3-Clause', 'GPL-2.0-only']);
	assert.deepEqual(alternatives(undefined), []);
});

test('un paquet est accepté si l\'une de ses licences est autorisée', () => {
	const result = check([
		{ name: 'a', version: '1', license: ['MIT'], source: 'npm' },
		{ name: 'nette/utils', version: '4', license: ['BSD-3-Clause', 'GPL-3.0-only'], source: 'composer' },
	], policy);
	assert.deepEqual(result.refused, []);
});

test('une licence GPL seule ou absente est refusée', () => {
	const result = check([
		{ name: 'mpdf/mpdf', version: '8', license: ['GPL-2.0-only'], source: 'composer' },
		{ name: 'inconnu', version: '1', license: [], source: 'npm' },
	], policy);
	assert.deepEqual(result.refused.map((p) => p.name), ['mpdf/mpdf', 'inconnu']);
});

test('une exception nommée autorise une licence précise pour un seul paquet', () => {
	const ok = check([{ name: 'openpgp', version: '6', license: ['LGPL-3.0-or-later'], source: 'npm' }], policy);
	const ko = check([{ name: 'autre', version: '1', license: ['LGPL-3.0-or-later'], source: 'npm' }], policy);
	assert.equal(ok.refused.length, 0);
	assert.equal(ko.refused.length, 1);
});

test('seules les dépendances livrées sont lues dans composer.lock', () => {
	const lock = { packages: [{ name: 'x/y', version: 'v1', license: ['MIT'] }], 'packages-dev': [{ name: 'dev/tool', license: ['GPL-3.0-only'] }] };
	assert.deepEqual(composerPackages(lock).map((p) => p.name), ['x/y']);
});

test('seules les dépendances livrées sont lues dans package-lock.json', () => {
	const lock = { packages: {
		'': { name: 'racine' },
		'node_modules/vue': { version: '3.5.0' },
		'node_modules/vitest': { version: '5.0.0', dev: true },
	} };
	const read = (dir) => ({ 'node_modules/vue': { license: 'MIT' } })[dir];
	assert.deepEqual(npmPackages(lock, read), [{ name: 'vue', version: '3.5.0', license: ['MIT'], source: 'npm' }]);
});
