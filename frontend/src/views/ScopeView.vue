<!--
	Périmètre réseau d'un test (engagement-4, 11.07, maquette 04 de 15.01) :
	machines et leurs services, comptes de test, inclus ou exclus avec
	commentaire et plages interdites. Lecture pour tous les membres de
	l'organisation ; ajout, modification et suppression par l'équipe du test
	(testeurs, chef de projet, administrateur).

	Aucun secret n'est saisi ici : celui d'un compte sera chiffré dans le
	navigateur avec la GED (ged-chiffree, 13.01).
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, deleteJson, getJson, patchJson, postJson } from '../api/client'
import type { PenetrationTest } from '../api/penetrationTests'
import { groupScope, SERVICE_PROTOCOLS, type ScopeObject, type ServiceProtocol } from '../api/scope'
import ActionIcon from '../components/ActionIcon.vue'
import LoadingThrobber from '../components/LoadingThrobber.vue'

const props = defineProps<{
	/** Identifiant (ULID) du test. */
	id: string
}>()

const { t } = useI18n()

/** Champs communs aux formulaires d'ajout. */
interface CommonFields {
	included: boolean
	comment: string
	constraints: string
}

/**
 * Champs communs vides : objet inclus, sans commentaire ni contrainte.
 *
 * @returns Une saisie vide.
 */
function emptyCommon(): CommonFields {
	return { included: true, comment: '', constraints: '' }
}

const test = ref<PenetrationTest | null>(null)
const objects = ref<ScopeObject[]>([])
const loading = ref(false)
const missing = ref(false)
const busy = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

const hostForm = ref({ address: '', hostname: '', operating_system: '', ...emptyCommon() })
const serviceForm = ref({ parent_id: '', protocol: 'tcp' as ServiceProtocol, port: '', name: '', version: '' })
const accountForm = ref({ parent_id: '', username: '', role_description: '' })

/** Objet en cours de modification, et sa saisie (champs communs et détail). */
const editing = ref<ScopeObject | null>(null)
const editForm = ref<Record<string, string>>({})
const editIncluded = ref(true)

/** Libellés des champs du détail proposés à la modification, dans l'ordre d'affichage. */
const DETAIL_LABELS: Record<string, string> = {
	address: 'scope.address',
	hostname: 'scope.hostname',
	operating_system: 'scope.operatingSystem',
	port: 'scope.port',
	name: 'scope.serviceName',
	version: 'scope.version',
	username: 'scope.username',
	role_description: 'scope.roleDescription',
}

/** Périmètre regroupé : machines et leurs services, comptes. */
const grouped = computed(() => groupScope(objects.value))

/** Vrai si l'utilisateur peut tenir le périmètre. */
const canManage = computed(() => test.value?.can_manage_scope === true)

/** Champs du détail de l'objet modifié. */
const editedDetailFields = computed(() => Object.keys(DETAIL_LABELS).filter((field) => field in editForm.value))

/**
 * Texte d'un champ facultatif : null pour une saisie vide.
 *
 * @param value Saisie.
 * @returns La saisie sans espaces de bord, ou null.
 */
function optional(value: string): string | null {
	return value.trim() === '' ? null : value.trim()
}

/**
 * Charge le test puis toutes les pages de son périmètre.
 *
 * @param id Identifiant du test.
 */
async function load(id: string): Promise<void> {
	loading.value = true
	try {
		const received = (await getJson<{ data: PenetrationTest }>(`/tests/${encodeURIComponent(id)}`)).data
		const all: ScopeObject[] = []
		for (let page = 1, last = 1; page <= last; page++) {
			const response = await getJson<{ data: ScopeObject[], meta: { last_page: number } }>(
				`/tests/${encodeURIComponent(id)}/scope-objects?page=${page}&sort=created_at&order=asc`,
			)
			all.push(...response.data)
			last = response.meta.last_page
		}
		// Réponse d'un test quitté entre-temps : ignorée.
		if (id === props.id) {
			test.value = received
			objects.value = all
		}
	} catch (caught) {
		if (id === props.id) {
			missing.value = caught instanceof ApiError && caught.status === 404
			error.value = missing.value ? null : t('auth.unexpected')
		}
	} finally {
		// Le chargement d'un test quitté ne masque pas celui du test affiché.
		if (id === props.id) {
			loading.value = false
		}
	}
}

/**
 * Ouvre un autre test : rien du précédent ne subsiste.
 *
 * @param id Identifiant du test.
 */
async function open(id: string): Promise<void> {
	test.value = null
	objects.value = []
	missing.value = false
	editing.value = null
	message.value = null
	error.value = null
	await load(id)
}

/**
 * Exécute une écriture, recharge le périmètre et annonce le résultat.
 *
 * @param request Requête d'écriture.
 * @param done Message en cas de succès.
 * @returns Vrai si l'écriture a réussi.
 */
async function write(request: () => Promise<unknown>, done: string): Promise<boolean> {
	busy.value = true
	error.value = null
	message.value = null
	const id = props.id
	try {
		await request()
		// Test quitté pendant l'écriture : ni rechargement ni message sur le nouveau.
		if (id !== props.id) {
			return true
		}
		await load(id)
		message.value = done
		return true
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
		return false
	} finally {
		busy.value = false
	}
}

/**
 * Déclare un objet dans le périmètre.
 *
 * @param body Type, champs communs et détail.
 * @returns Vrai si l'objet a été ajouté.
 */
function declare(body: Record<string, unknown>): Promise<boolean> {
	return write(() => postJson(`/tests/${encodeURIComponent(props.id)}/scope-objects`, body), t('scope.created'))
}

/** Ajoute une machine, puis vide le formulaire. */
async function addHost(): Promise<void> {
	const form = hostForm.value
	const added = await declare({
		type: 'host',
		address: form.address.trim(),
		hostname: optional(form.hostname),
		operating_system: optional(form.operating_system),
		included: form.included,
		comment: optional(form.comment),
		constraints: optional(form.constraints),
	})
	if (added) {
		hostForm.value = { address: '', hostname: '', operating_system: '', ...emptyCommon() }
	}
}

/** Ajoute un service à une machine, puis vide le formulaire (machine et protocole gardés). */
async function addService(): Promise<void> {
	const form = serviceForm.value
	const added = await declare({
		type: 'service',
		parent_id: form.parent_id,
		protocol: form.protocol,
		port: Number(form.port),
		name: optional(form.name),
		version: optional(form.version),
	})
	if (added) {
		serviceForm.value = { parent_id: form.parent_id, protocol: form.protocol, port: '', name: '', version: '' }
	}
}

/** Ajoute un compte de test, puis vide le formulaire. */
async function addAccount(): Promise<void> {
	const form = accountForm.value
	const added = await declare({
		type: 'account',
		parent_id: optional(form.parent_id),
		username: form.username.trim(),
		role_description: optional(form.role_description),
	})
	if (added) {
		accountForm.value = { parent_id: '', username: '', role_description: '' }
	}
}

/**
 * Inclut ou exclut un objet.
 *
 * @param object Objet visé.
 */
async function toggle(object: ScopeObject): Promise<void> {
	await write(() => patchJson(`/scope-objects/${encodeURIComponent(object.id)}`, { included: !object.included }), t('common.saved'))
}

/**
 * Ouvre la modification d'un objet, préremplie.
 *
 * @param object Objet à modifier.
 */
function edit(object: ScopeObject): void {
	const detail = object.host ?? object.service ?? object.account ?? {}
	const fields: Record<string, string> = {
		label: object.label,
		comment: object.comment ?? '',
		constraints: object.constraints ?? '',
	}
	for (const [key, value] of Object.entries(detail)) {
		fields[key] = value === null ? '' : String(value)
	}
	editForm.value = fields
	editIncluded.value = object.included
	editing.value = object
	message.value = null
}

/** Enregistre la modification en cours ; un libellé vide redevient celui déduit du détail. */
async function saveEdit(): Promise<void> {
	const object = editing.value
	if (!object) {
		return
	}
	const body: Record<string, unknown> = { included: editIncluded.value }
	for (const [key, value] of Object.entries(editForm.value)) {
		if (key === 'port') {
			body[key] = Number(value)
		} else if (key === 'protocol') {
			body[key] = value
		} else {
			body[key] = optional(value)
		}
	}
	if (await write(() => patchJson(`/scope-objects/${encodeURIComponent(object.id)}`, body), t('common.saved'))) {
		editing.value = null
	}
}

/**
 * Supprime un objet après confirmation.
 *
 * @param object Objet à supprimer.
 */
async function remove(object: ScopeObject): Promise<void> {
	if (globalThis.confirm(t('scope.confirmDelete', { label: object.label }))) {
		await write(() => deleteJson(`/scope-objects/${encodeURIComponent(object.id)}`), t('scope.deleted'))
	}
}

watch(() => props.id, open, { immediate: true })
</script>

<template>
	<p v-if="missing">
		{{ t('scope.notFound') }}
	</p>
	<p
		v-if="error"
		role="alert"
	>
		{{ error }}
	</p>
	<p
		v-if="message"
		role="status"
	>
		{{ message }}
	</p>
	<LoadingThrobber
		v-if="loading && !test"
		:label="t('common.loading')"
		visible-label
	/>
	<template v-if="test">
		<p>
			<RouterLink :to="{ name: 'test', params: { id: test.id } }">
				{{ t('scope.back') }}
			</RouterLink>
		</p>
		<h2>{{ t('scope.heading', { test: `${test.reference} — ${test.title}` }) }}</h2>
		<p>{{ t('scope.noSecret') }}</p>

		<h3>{{ t('scope.hosts') }}</h3>
		<p v-if="grouped.hosts.length === 0">
			{{ t('scope.empty') }}
		</p>
		<table v-else>
			<caption>{{ t('scope.hostsCaption') }}</caption>
			<thead>
				<tr>
					<th scope="col">
						{{ t('scope.host') }}
					</th>
					<th scope="col">
						{{ t('scope.services') }}
					</th>
					<th scope="col">
						{{ t('scope.inclusion') }}
					</th>
					<th scope="col">
						{{ t('scope.constraints') }}
					</th>
					<th scope="col">
						{{ t('scope.comment') }}
					</th>
					<th
						v-if="canManage"
						scope="col"
					>
						{{ t('scope.actions') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="entry in grouped.hosts"
					:key="entry.host.id"
				>
					<th scope="row">
						{{ entry.host.label }}<br>
						<small>{{ entry.host.host?.address }}<template v-if="entry.host.host?.operating_system"> · {{ entry.host.host.operating_system }}</template></small>
					</th>
					<td>
						<span v-if="entry.services.length === 0">{{ t('scope.noService') }}</span>
						<ul v-else>
							<li
								v-for="service in entry.services"
								:key="service.id"
							>
								{{ service.label }}<template v-if="service.service?.version">
									({{ service.service.version }})
								</template>
								<template v-if="!service.included">
									— {{ t('scope.excluded') }}
								</template>
								<template v-if="canManage">
									<button
										type="button"
										:disabled="busy"
										:aria-label="t('scope.editObject', { label: service.label })"
										@click="edit(service)"
									>
										<ActionIcon name="edit" />
									</button>
									<button
										type="button"
										:disabled="busy"
										:aria-label="t('scope.deleteObject', { label: service.label })"
										@click="remove(service)"
									>
										<ActionIcon name="delete" />
									</button>
								</template>
							</li>
						</ul>
					</td>
					<td>{{ entry.host.included ? t('scope.included') : t('scope.excluded') }}</td>
					<td>{{ entry.host.constraints ?? '' }}</td>
					<td>{{ entry.host.comment ?? '' }}</td>
					<td v-if="canManage">
						<button
							type="button"
							:disabled="busy"
							@click="toggle(entry.host)"
						>
							{{ entry.host.included ? t('scope.exclude', { label: entry.host.label }) : t('scope.include', { label: entry.host.label }) }}
						</button>
						<button
							type="button"
							:disabled="busy"
							@click="edit(entry.host)"
						>
							<ActionIcon name="edit" />{{ t('scope.editObject', { label: entry.host.label }) }}
						</button>
						<button
							type="button"
							:disabled="busy"
							@click="remove(entry.host)"
						>
							<ActionIcon name="delete" />{{ t('scope.deleteObject', { label: entry.host.label }) }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>

		<h3>{{ t('scope.accounts') }}</h3>
		<p v-if="grouped.accounts.length === 0">
			{{ t('scope.empty') }}
		</p>
		<table v-else>
			<caption>{{ t('scope.accountsCaption') }}</caption>
			<thead>
				<tr>
					<th scope="col">
						{{ t('scope.username') }}
					</th>
					<th scope="col">
						{{ t('scope.roleDescription') }}
					</th>
					<th scope="col">
						{{ t('scope.host') }}
					</th>
					<th scope="col">
						{{ t('scope.inclusion') }}
					</th>
					<th scope="col">
						{{ t('scope.comment') }}
					</th>
					<th
						v-if="canManage"
						scope="col"
					>
						{{ t('scope.actions') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="entry in grouped.accounts"
					:key="entry.account.id"
				>
					<th scope="row">
						{{ entry.account.label }}
					</th>
					<td>{{ entry.account.account?.role_description ?? '' }}</td>
					<td>{{ entry.hostLabel ?? t('scope.noHost') }}</td>
					<td>{{ entry.account.included ? t('scope.included') : t('scope.excluded') }}</td>
					<td>{{ entry.account.comment ?? '' }}</td>
					<td v-if="canManage">
						<button
							type="button"
							:disabled="busy"
							@click="edit(entry.account)"
						>
							<ActionIcon name="edit" />{{ t('scope.editObject', { label: entry.account.label }) }}
						</button>
						<button
							type="button"
							:disabled="busy"
							@click="remove(entry.account)"
						>
							<ActionIcon name="delete" />{{ t('scope.deleteObject', { label: entry.account.label }) }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>

		<form
			v-if="editing"
			@submit.prevent="saveEdit"
		>
			<fieldset>
				<legend>{{ t('scope.editing', { label: editing.label }) }}</legend>
				<label for="perimetre-modif-libelle">{{ t('scope.label') }}</label>
				<input
					id="perimetre-modif-libelle"
					v-model="editForm.label"
					maxlength="255"
				>
				<template
					v-for="field in editedDetailFields"
					:key="field"
				>
					<label :for="`perimetre-modif-${field}`">{{ t(DETAIL_LABELS[field] ?? field) }}</label>
					<input
						:id="`perimetre-modif-${field}`"
						v-model="editForm[field]"
						:type="field === 'port' ? 'number' : 'text'"
					>
				</template>
				<label for="perimetre-modif-contraintes">{{ t('scope.constraints') }}</label>
				<textarea
					id="perimetre-modif-contraintes"
					v-model="editForm.constraints"
					maxlength="2000"
				/>
				<label for="perimetre-modif-commentaire">{{ t('scope.comment') }}</label>
				<textarea
					id="perimetre-modif-commentaire"
					v-model="editForm.comment"
					maxlength="2000"
				/>
				<label>
					<input
						v-model="editIncluded"
						type="checkbox"
					> {{ t('scope.includedField') }}
				</label>
				<p>
					<button
						type="submit"
						:disabled="busy"
					>
						<ActionIcon
							name="save"
							:busy="busy"
						/>{{ t('common.save') }}
					</button>
					<button
						type="button"
						@click="editing = null"
					>
						<ActionIcon name="cancel" />{{ t('common.cancel') }}
					</button>
				</p>
			</fieldset>
		</form>

		<template v-if="canManage && !editing">
			<form @submit.prevent="addHost">
				<fieldset>
					<legend>{{ t('scope.addHost') }}</legend>
					<label for="perimetre-adresse">{{ t('scope.address') }}</label>
					<input
						id="perimetre-adresse"
						v-model="hostForm.address"
						required
						maxlength="64"
					>
					<label for="perimetre-nom-machine">{{ t('scope.hostname') }}</label>
					<input
						id="perimetre-nom-machine"
						v-model="hostForm.hostname"
						maxlength="255"
					>
					<label for="perimetre-systeme">{{ t('scope.operatingSystem') }}</label>
					<input
						id="perimetre-systeme"
						v-model="hostForm.operating_system"
						maxlength="255"
					>
					<label for="perimetre-machine-contraintes">{{ t('scope.constraints') }}</label>
					<textarea
						id="perimetre-machine-contraintes"
						v-model="hostForm.constraints"
						maxlength="2000"
					/>
					<label for="perimetre-machine-commentaire">{{ t('scope.comment') }}</label>
					<textarea
						id="perimetre-machine-commentaire"
						v-model="hostForm.comment"
						maxlength="2000"
					/>
					<label>
						<input
							v-model="hostForm.included"
							type="checkbox"
						> {{ t('scope.includedField') }}
					</label>
					<button
						type="submit"
						:disabled="busy"
					>
						<ActionIcon
							name="create"
							:busy="busy"
						/>{{ t('scope.addHost') }}
					</button>
				</fieldset>
			</form>

			<form
				v-if="grouped.hosts.length > 0"
				@submit.prevent="addService"
			>
				<fieldset>
					<legend>{{ t('scope.addService') }}</legend>
					<label for="perimetre-service-machine">{{ t('scope.host') }}</label>
					<select
						id="perimetre-service-machine"
						v-model="serviceForm.parent_id"
						required
					>
						<option
							v-for="entry in grouped.hosts"
							:key="entry.host.id"
							:value="entry.host.id"
						>
							{{ entry.host.label }}
						</option>
					</select>
					<label for="perimetre-protocole">{{ t('scope.protocol') }}</label>
					<select
						id="perimetre-protocole"
						v-model="serviceForm.protocol"
					>
						<option
							v-for="protocol in SERVICE_PROTOCOLS"
							:key="protocol"
							:value="protocol"
						>
							{{ t(`scope.protocols.${protocol}`) }}
						</option>
					</select>
					<label for="perimetre-port">{{ t('scope.port') }}</label>
					<input
						id="perimetre-port"
						v-model="serviceForm.port"
						type="number"
						min="1"
						max="65535"
						required
					>
					<label for="perimetre-nom-service">{{ t('scope.serviceName') }}</label>
					<input
						id="perimetre-nom-service"
						v-model="serviceForm.name"
						maxlength="64"
					>
					<label for="perimetre-version">{{ t('scope.version') }}</label>
					<input
						id="perimetre-version"
						v-model="serviceForm.version"
						maxlength="255"
					>
					<button
						type="submit"
						:disabled="busy"
					>
						<ActionIcon
							name="create"
							:busy="busy"
						/>{{ t('scope.addService') }}
					</button>
				</fieldset>
			</form>

			<form @submit.prevent="addAccount">
				<fieldset>
					<legend>{{ t('scope.addAccount') }}</legend>
					<label for="perimetre-identifiant">{{ t('scope.username') }}</label>
					<input
						id="perimetre-identifiant"
						v-model="accountForm.username"
						required
						maxlength="255"
						autocomplete="off"
					>
					<label for="perimetre-role">{{ t('scope.roleDescription') }}</label>
					<input
						id="perimetre-role"
						v-model="accountForm.role_description"
						maxlength="255"
					>
					<label for="perimetre-compte-machine">{{ t('scope.host') }}</label>
					<select
						id="perimetre-compte-machine"
						v-model="accountForm.parent_id"
					>
						<option value="">
							{{ t('scope.noHost') }}
						</option>
						<option
							v-for="entry in grouped.hosts"
							:key="entry.host.id"
							:value="entry.host.id"
						>
							{{ entry.host.label }}
						</option>
					</select>
					<button
						type="submit"
						:disabled="busy"
					>
						<ActionIcon
							name="create"
							:busy="busy"
						/>{{ t('scope.addAccount') }}
					</button>
				</fieldset>
			</form>
		</template>
	</template>
</template>
