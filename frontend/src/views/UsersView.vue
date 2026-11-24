<!--
	Administration des utilisateurs (identite-7, écran 16 de 15.01, 11.06).

	- Onglet Utilisateurs (administrateurs d'instance) : état de chaque compte
	  (actif, invité, verrouillé, désactivé), désactivation et réactivation,
	  déverrouillage anticipé, lien de réinitialisation du mot de passe,
	  suppression d'un compte désactivé après saisie de « SUPPRIMER ».
	- Onglet Invitations : invitations en attente, renvoi (nouveau lien) et
	  annulation ; formulaire d'invitation. Un chef de projet n'y invite que des
	  clients finaux de ses organisations, et ne voit que ses invitations.

	Aucun e-mail n'est envoyé en V1 : chaque lien émis s'affiche une seule fois,
	à transmettre par un autre canal.
-->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
	cancelInvitation,
	deleteUser,
	invite,
	issuePasswordReset,
	listInvitations,
	listUsers,
	resendInvitation,
	userAction,
	type Invitation,
	type ManagedUser,
} from '../api/accounts'
import { ApiError, getJson } from '../api/client'
import type { Organisation, OrganisationRole } from '../api/organisations'
import { useAuthStore } from '../stores/auth'
import { formatDateTime } from '../utils/dates'
import ActionIcon from '../components/ActionIcon.vue'
import IssuedLink from '../components/IssuedLink.vue'
import LoadingThrobber from '../components/LoadingThrobber.vue'

/** Mot à saisir pour confirmer une suppression (maquette 16). */
const CONFIRMATION_WORD = 'SUPPRIMER'

const { t, locale } = useI18n()
const auth = useAuthStore()

const isAdmin = computed(() => auth.user?.is_instance_admin === true)
const tab = ref<'users' | 'invitations'>(isAdmin.value ? 'users' : 'invitations')
const users = ref<ManagedUser[] | null>(null)
const invitations = ref<Invitation[] | null>(null)
const organisations = ref<Organisation[]>([])
const busy = ref<string | null>(null)
const error = ref<string | null>(null)
/** Dernier lien émis, affiché une seule fois. */
const issued = ref<{ link: string, intro: string } | null>(null)

/** Formulaire d'invitation. */
const form = ref({ email: '', name: '', tenantId: '', role: 'client' as OrganisationRole, admin: false })
/** Compte dont la suppression est en cours de confirmation. */
const deleting = ref<ManagedUser | null>(null)
const confirmation = ref('')

/** Organisations où l'on peut inviter : toutes pour un administrateur, celles où l'on est chef de projet sinon. */
const invitableOrganisations = computed(() => organisations.value.filter((organisation) => isAdmin.value || organisation.role === 'project_manager'))
/** Rôles proposés : un chef de projet n'invite que des clients finaux. */
const roles = computed<OrganisationRole[]>(() => (isAdmin.value ? ['project_manager', 'tester', 'client'] : ['client']))

/**
 * Message lisible d'une erreur d'API.
 *
 * @param caught Erreur levée.
 * @returns Le message traduit.
 */
function describe(caught: unknown): string {
	return caught instanceof ApiError ? caught.firstMessage() : t('auth.unexpected')
}

/**
 * Date lisible, ou chaîne vide.
 *
 * @param iso Date ISO 8601, ou null.
 * @returns La date formatée.
 */
function when(iso: string | null): string {
	return iso ? formatDateTime(iso, locale.value) ?? '' : ''
}

/** Charge les comptes (administrateurs), les invitations et les organisations. */
async function load(): Promise<void> {
	try {
		const [loadedInvitations, loadedOrganisations] = await Promise.all([
			listInvitations(),
			getJson<{ data: Organisation[] }>('/tenants?sort=name&order=asc&limit=200'),
		])
		invitations.value = loadedInvitations
		organisations.value = loadedOrganisations.data
		form.value.tenantId ||= invitableOrganisations.value[0]?.id ?? ''
		if (isAdmin.value) {
			users.value = await listUsers()
		}
	} catch (caught) {
		error.value = describe(caught)
	}
}

/**
 * Affiche un onglet et relit les listes : un compte invité a pu s'activer entre-temps.
 *
 * @param name Onglet à afficher.
 */
async function selectTab(name: 'users' | 'invitations'): Promise<void> {
	tab.value = name
	await load()
}

/**
 * Exécute une action, puis recharge les listes ; l'erreur s'affiche dans la page.
 *
 * @param key Identifiant de l'action en cours (bouton occupé).
 * @param action Action à exécuter.
 */
async function run(key: string, action: () => Promise<void>): Promise<void> {
	busy.value = key
	error.value = null
	try {
		await action()
		await load()
	} catch (caught) {
		error.value = describe(caught)
	} finally {
		busy.value = null
	}
}

/** Envoie l'invitation et affiche son lien, ou le rattachement direct d'un compte actif. */
async function submitInvitation(): Promise<void> {
	const email = form.value.email.trim()
	await run('invite', async () => {
		const request = form.value.admin
			? { email, name: form.value.name || undefined, is_instance_admin: true }
			: { email, name: form.value.name || undefined, tenant_id: form.value.tenantId, role: form.value.role }
		const result = await invite(request)
		issued.value = result.link
			? { link: result.link, intro: t('accounts.invitationLink', { email, date: when(result.data.expires_at) }) }
			: { link: '', intro: t('accounts.attached', { email }) }
		form.value.email = ''
		form.value.name = ''
	})
}

/**
 * Renvoie une invitation : le nouveau lien remplace l'ancien.
 *
 * @param invitation Invitation en attente.
 */
async function resend(invitation: Invitation): Promise<void> {
	await run(`resend-${invitation.id}`, async () => {
		const result = await resendInvitation(invitation.id)
		issued.value = { link: result.link, intro: t('accounts.invitationLink', { email: invitation.email, date: when(result.data.expires_at) }) }
	})
}

/**
 * Émet un lien de réinitialisation du mot de passe.
 *
 * @param user Compte actif avec coffre.
 */
async function reset(user: ManagedUser): Promise<void> {
	await run(`reset-${user.id}`, async () => {
		const result = await issuePasswordReset(user.id)
		issued.value = { link: result.link, intro: t('accounts.resetLink', { email: user.email, date: when(result.expires_at) }) }
	})
}

/** Supprime le compte confirmé. */
async function confirmDeletion(): Promise<void> {
	const target = deleting.value
	if (!target || confirmation.value !== CONFIRMATION_WORD) {
		return
	}
	await run(`delete-${target.id}`, async () => {
		await deleteUser(target.id)
		deleting.value = null
		confirmation.value = ''
	})
}

onMounted(load)
</script>

<template>
	<h2>{{ t('accounts.heading') }}</h2>
	<div
		class="tabs"
		role="tablist"
		:aria-label="t('accounts.tabsLabel')"
	>
		<button
			v-if="isAdmin"
			type="button"
			role="tab"
			:aria-selected="tab === 'users'"
			@click="selectTab('users')"
		>
			{{ t('accounts.usersTab', { count: users?.length ?? 0 }) }}
		</button>
		<button
			type="button"
			role="tab"
			:aria-selected="tab === 'invitations'"
			@click="selectTab('invitations')"
		>
			{{ t('accounts.invitationsTab', { count: invitations?.length ?? 0 }) }}
		</button>
	</div>

	<p
		v-if="error"
		role="alert"
	>
		{{ error }}
	</p>
	<IssuedLink
		v-if="issued?.link"
		:link="issued.link"
		:intro="issued.intro"
	/>
	<p
		v-else-if="issued"
		role="status"
	>
		{{ issued.intro }}
	</p>

	<section
		v-if="tab === 'users' && isAdmin"
		:aria-label="t('accounts.usersHeading')"
	>
		<p v-if="users === null">
			<LoadingThrobber
				:label="t('common.loading')"
				visible-label
			/>
		</p>
		<table v-else>
			<thead>
				<tr>
					<th scope="col">
						{{ t('accounts.name') }}
					</th>
					<th scope="col">
						{{ t('auth.email') }}
					</th>
					<th scope="col">
						{{ t('nav.organisations') }}
					</th>
					<th scope="col">
						{{ t('accounts.status') }}
					</th>
					<th scope="col">
						{{ t('accounts.twoFactor') }}
					</th>
					<th scope="col">
						{{ t('accounts.actions') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="user in users"
					:key="user.id"
					:data-email="user.email"
				>
					<td>
						{{ user.name }}
						<small v-if="user.is_instance_admin">{{ t('accounts.admin') }}</small>
					</td>
					<td>{{ user.email }}</td>
					<td>
						<ul>
							<li
								v-for="tenant in user.tenants"
								:key="tenant.id"
							>
								{{ tenant.name }} ({{ t(`roles.${tenant.role}`) }})
							</li>
						</ul>
					</td>
					<td>
						{{ t(`accounts.statuses.${user.status}`) }}
						<small v-if="user.status === 'locked' && user.locked_until">{{ t('accounts.lockedUntil', { date: when(user.locked_until) }) }}</small>
					</td>
					<td>{{ user.two_factor_enabled ? t('accounts.yes') : t('accounts.no') }}</td>
					<td class="row-actions">
						<template v-if="user.status === 'active' || user.status === 'locked'">
							<button
								v-if="user.status === 'locked'"
								type="button"
								:disabled="busy !== null"
								@click="run(`unlock-${user.id}`, async () => { await userAction(user.id, 'unlock') })"
							>
								<ActionIcon
									name="key"
									:busy="busy === `unlock-${user.id}`"
								/>{{ t('accounts.unlock') }}
							</button>
							<button
								v-if="user.has_vault"
								type="button"
								:disabled="busy !== null"
								@click="reset(user)"
							>
								<ActionIcon
									name="recovery"
									:busy="busy === `reset-${user.id}`"
								/>{{ t('accounts.resetPassword') }}
							</button>
							<button
								v-if="user.id !== auth.user?.id"
								type="button"
								:disabled="busy !== null"
								@click="run(`deactivate-${user.id}`, async () => { await userAction(user.id, 'deactivate') })"
							>
								<ActionIcon
									name="cancel"
									:busy="busy === `deactivate-${user.id}`"
								/>{{ t('accounts.deactivate') }}
							</button>
						</template>
						<template v-else-if="user.status === 'deactivated'">
							<button
								type="button"
								:disabled="busy !== null"
								@click="run(`reactivate-${user.id}`, async () => { await userAction(user.id, 'reactivate') })"
							>
								<ActionIcon
									name="refresh"
									:busy="busy === `reactivate-${user.id}`"
								/>{{ t('accounts.reactivate') }}
							</button>
							<button
								type="button"
								:disabled="busy !== null"
								@click="deleting = user; confirmation = ''"
							>
								<ActionIcon name="delete" />{{ t('accounts.delete') }}
							</button>
						</template>
						<small v-else-if="user.activation_expires_at">{{ t('accounts.invitedUntil', { date: when(user.activation_expires_at) }) }}</small>
					</td>
				</tr>
			</tbody>
		</table>

		<form
			v-if="deleting"
			class="panel"
			role="alertdialog"
			aria-labelledby="suppression-titre"
			@submit.prevent="confirmDeletion"
		>
			<h3 id="suppression-titre">
				{{ t('accounts.deleteHeading', { name: deleting.name }) }}
			</h3>
			<p>{{ t('accounts.deleteWarning') }}</p>
			<p>{{ t('accounts.deleteNoContent') }}</p>
			<label for="suppression-confirmation">{{ t('accounts.deleteConfirm', { word: CONFIRMATION_WORD }) }}</label>
			<input
				id="suppression-confirmation"
				v-model="confirmation"
				autocomplete="off"
			>
			<div>
				<button
					type="button"
					@click="deleting = null"
				>
					<ActionIcon name="cancel" />{{ t('accounts.cancel') }}
				</button>
				<button
					type="submit"
					:disabled="confirmation !== CONFIRMATION_WORD || busy !== null"
				>
					<ActionIcon
						name="delete"
						:busy="busy === `delete-${deleting.id}`"
					/>{{ t('accounts.deleteForever') }}
				</button>
			</div>
		</form>
	</section>

	<section
		v-if="tab === 'invitations'"
		:aria-label="t('accounts.invitationsHeading')"
	>
		<p v-if="invitations === null">
			<LoadingThrobber
				:label="t('common.loading')"
				visible-label
			/>
		</p>
		<p v-else-if="invitations.length === 0">
			{{ t('accounts.noInvitation') }}
		</p>
		<table v-else>
			<thead>
				<tr>
					<th scope="col">
						{{ t('auth.email') }}
					</th>
					<th scope="col">
						{{ t('accounts.target') }}
					</th>
					<th scope="col">
						{{ t('accounts.expires') }}
					</th>
					<th scope="col">
						{{ t('accounts.invitedBy') }}
					</th>
					<th scope="col">
						{{ t('accounts.actions') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="invitation in invitations"
					:key="invitation.id"
					:data-email="invitation.email"
				>
					<td>{{ invitation.email }}</td>
					<td>
						<template v-if="invitation.tenant && invitation.role">
							{{ invitation.tenant.name }} ({{ t(`roles.${invitation.role}`) }})
						</template>
						<template v-if="invitation.is_instance_admin">
							{{ t('accounts.admin') }}
						</template>
					</td>
					<td>{{ invitation.status === 'expired' ? t('accounts.expired') : when(invitation.expires_at) }}</td>
					<td>{{ invitation.invited_by?.name ?? '' }}</td>
					<td class="row-actions">
						<button
							type="button"
							:disabled="busy !== null"
							@click="resend(invitation)"
						>
							<ActionIcon
								name="refresh"
								:busy="busy === `resend-${invitation.id}`"
							/>{{ t('accounts.resend') }}
						</button>
						<button
							type="button"
							:disabled="busy !== null"
							@click="run(`cancel-${invitation.id}`, () => cancelInvitation(invitation.id))"
						>
							<ActionIcon
								name="cancel"
								:busy="busy === `cancel-${invitation.id}`"
							/>{{ t('accounts.cancelInvitation') }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>

		<form
			class="panel"
			@submit.prevent="submitInvitation"
		>
			<h3>{{ t('accounts.invite') }}</h3>
			<label for="invitation-email">{{ t('auth.email') }}</label>
			<input
				id="invitation-email"
				v-model="form.email"
				type="email"
				required
				maxlength="255"
				autocomplete="off"
			>
			<label for="invitation-nom">{{ t('accounts.nameOptional') }}</label>
			<input
				id="invitation-nom"
				v-model="form.name"
				maxlength="255"
				autocomplete="off"
			>
			<label v-if="isAdmin">
				<input
					v-model="form.admin"
					type="checkbox"
				>
				{{ t('accounts.inviteAdmin') }}
			</label>
			<template v-if="!form.admin">
				<label for="invitation-organisation">{{ t('accounts.organisation') }}</label>
				<select
					id="invitation-organisation"
					v-model="form.tenantId"
					required
				>
					<option
						v-for="organisation in invitableOrganisations"
						:key="organisation.id"
						:value="organisation.id"
					>
						{{ organisation.name }}
					</option>
				</select>
				<label for="invitation-role">{{ t('accounts.role') }}</label>
				<select
					id="invitation-role"
					v-model="form.role"
				>
					<option
						v-for="role in roles"
						:key="role"
						:value="role"
					>
						{{ t(`roles.${role}`) }}
					</option>
				</select>
			</template>
			<button
				type="submit"
				:disabled="busy !== null"
			>
				<ActionIcon
					name="create"
					:busy="busy === 'invite'"
				/>{{ t('accounts.sendInvitation') }}
			</button>
		</form>
	</section>
</template>
