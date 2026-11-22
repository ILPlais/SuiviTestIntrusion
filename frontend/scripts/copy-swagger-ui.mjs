/**
 * Copie Swagger UI (paquet swagger-ui-dist) dans public/swagger-ui avant le build
 * (socle-6) : la page /api/documentation charge ces fichiers depuis l'instance
 * elle-même, sans service tiers (CSP de 13.04, instances sans accès Internet).
 *
 * Usage : node scripts/copy-swagger-ui.mjs (lancé par « npm run build »).
 */
import { copyFileSync, mkdirSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const require = createRequire(import.meta.url)
const source = dirname(require.resolve('swagger-ui-dist/package.json'))
const target = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'swagger-ui')

mkdirSync(target, { recursive: true })
for (const file of ['swagger-ui.css', 'swagger-ui-bundle.js', 'LICENSE']) {
	copyFileSync(join(source, file), join(target, file))
}
console.log(`Swagger UI copié dans ${target}`)
