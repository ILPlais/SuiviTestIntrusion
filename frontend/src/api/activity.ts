/**
 * Requêtes en cours vers l'API (socle-8) : le client y inscrit chaque appel,
 * l'en-tête affiche un throbber tant qu'il en reste une.
 */
import { readonly, ref } from 'vue'

/** Nombre de requêtes en cours (modifiable ici seulement). */
const count = ref(0)

/** Nombre de requêtes en cours, en lecture seule pour l'interface. */
export const activeRequests = readonly(count)

/**
 * Compte une requête pendant son exécution, qu'elle réussisse ou échoue.
 *
 * @param work Promesse de la requête.
 * @returns La même promesse, résolue ou rejetée comme elle.
 */
export async function track<T>(work: Promise<T>): Promise<T> {
	count.value++
	try {
		return await work
	} finally {
		count.value--
	}
}
