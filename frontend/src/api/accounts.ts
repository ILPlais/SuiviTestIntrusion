/**
 * Cycle de vie des comptes (identite-7, 11.06) : types et appels de l'API
 * d'administration des utilisateurs et des invitations.
 */

import { deleteJson, getJson, postJson } from './client'
import type { OrganisationRole } from './organisations'

/** État d'un compte affiché à l'administration (écran 16 de 15.01). */
export type AccountStatus = 'active' | 'invited' | 'locked' | 'deactivated'

/** Compte de l'instance, tel que le renvoie GET /api/v1/users. */
export interface ManagedUser {
	id: number
	name: string
	email: string
	status: AccountStatus
	is_instance_admin: boolean
	two_factor_enabled: boolean
	has_vault: boolean
	/** Fin du verrouillage en cours, sinon null. */
	locked_until: string | null
	deactivated_at: string | null
	/** Échéance du lien d'activation d'un compte invité. */
	activation_expires_at: string | null
	tenants: { id: string, name: string, role: OrganisationRole }[]
}

/** Invitation en attente, telle que la renvoie GET /api/v1/invitations. */
export interface Invitation {
	id: string
	email: string
	name: string
	tenant: { id: string, name: string } | null
	role: OrganisationRole | null
	is_instance_admin: boolean
	status: 'pending' | 'expired' | 'accepted'
	expires_at: string | null
	invited_by: { id: number, name: string } | null
	created_at: string | null
}

/** Demande d'invitation. */
export interface InvitationRequest {
	email: string
	name?: string
	tenant_id?: string
	role?: OrganisationRole
	is_instance_admin?: boolean
}

/** Lien à usage unique à transmettre par un autre canal (aucun e-mail en V1). */
export interface IssuedLink {
	link: string
	expires_at: string
}

/**
 * Comptes de l'instance (administrateurs d'instance).
 *
 * @returns Les comptes, triés par nom.
 */
export async function listUsers(): Promise<ManagedUser[]> {
	return (await getJson<{ data: ManagedUser[] }>('/users?sort=name&order=asc&limit=200')).data
}

/**
 * Invitations en attente : toutes pour un administrateur, les siennes pour un chef de projet.
 *
 * @returns Les invitations, les plus récentes d'abord.
 */
export async function listInvitations(): Promise<Invitation[]> {
	return (await getJson<{ data: Invitation[] }>('/invitations?limit=200')).data
}

/**
 * Invite un compte. Le lien est null quand un compte actif a été rattaché directement.
 *
 * @param request Adresse, organisation et rôle, ou rôle d'administrateur.
 * @returns L'invitation et son lien éventuel.
 */
export function invite(request: InvitationRequest): Promise<{ data: Invitation, link: string | null }> {
	return postJson('/invitations', request)
}

/**
 * Renvoie une invitation : nouveau lien, l'ancien ne vaut plus.
 *
 * @param id Identifiant de l'invitation.
 * @returns L'invitation et son nouveau lien.
 */
export function resendInvitation(id: string): Promise<{ data: Invitation, link: string }> {
	return postJson(`/invitations/${encodeURIComponent(id)}/resend`)
}

/**
 * Annule une invitation en attente.
 *
 * @param id Identifiant de l'invitation.
 */
export async function cancelInvitation(id: string): Promise<void> {
	await deleteJson(`/invitations/${encodeURIComponent(id)}`)
}

/**
 * Action d'administration sur un compte, qui renvoie le compte mis à jour.
 *
 * @param id Identifiant du compte.
 * @param action Désactivation, réactivation ou déverrouillage.
 * @returns Le compte mis à jour.
 */
export async function userAction(id: number, action: 'deactivate' | 'reactivate' | 'unlock'): Promise<ManagedUser> {
	return (await postJson<{ data: ManagedUser }>(`/users/${id}/${action}`)).data
}

/**
 * Supprime définitivement un compte désactivé (pseudonymisé).
 *
 * @param id Identifiant du compte.
 */
export async function deleteUser(id: number): Promise<void> {
	await deleteJson(`/users/${id}`)
}

/**
 * Émet un lien de réinitialisation du mot de passe, valable 24 heures.
 *
 * @param id Identifiant du compte.
 * @returns Le lien et son échéance.
 */
export function issuePasswordReset(id: number): Promise<IssuedLink> {
	return postJson(`/users/${id}/password-reset`)
}
