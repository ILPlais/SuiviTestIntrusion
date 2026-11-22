<?php

namespace App\Enums;

/**
 * Rôle d'un utilisateur dans une organisation (identite-5, 11.06, 14.01).
 *
 * Les rôles « root » et « administrateur » de 11.06 ne dépendent pas d'une
 * organisation : ce sont les administrateurs d'instance (`users.is_instance_admin`),
 * qui voient toutes les organisations ; root est le premier d'entre eux, créé par
 * la commande d'installation `suivi:create-admin`.
 */
enum TenantRole: string
{
	/** Chef de projet : cadre, planifie et vérifie les tests de l'organisation. */
	case ProjectManager = 'project_manager';

	/** Testeur d'intrusion : réalise les tâches qui lui sont attribuées. */
	case Tester = 'tester';

	/** Client final : lit les tests et le rapport de son organisation uniquement. */
	case Client = 'client';
}
