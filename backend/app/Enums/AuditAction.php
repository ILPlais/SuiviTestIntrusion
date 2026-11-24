<?php

namespace App\Enums;

/**
 * Catalogue des actions consignées dans le journal d'audit (identite-6, 11.02).
 *
 * Le CDC fixe les événements minimaux : connexions, cycle de vie des comptes,
 * changements d'ACL, partage et révocation de documents, transferts de
 * propriété, demandes de rechiffrement, exports, changements de statut de test
 * ou de remédiation, validation client. Les actions dont la fonctionnalité
 * n'existe pas encore sont déjà réservées ici ; la story qui l'apporte les émet
 * (identite-7 pour les comptes et les ACL, ged-chiffree-* pour les documents,
 * exports-* pour les exports, resultats-* pour la validation client).
 *
 * La valeur, stable, est stockée en base et sert de filtre dans l'API :
 * « objet.verbe », en anglais comme les autres valeurs de l'API.
 */
enum AuditAction: string
{
	// Connexions (identite-1, identite-3).

	/** Connexion réussie (après le second facteur s'il est actif). */
	case LoginSucceeded = 'auth.login';

	/** Connexion refusée : adresse inconnue, valeur fausse ou compte verrouillé. */
	case LoginFailed = 'auth.login_failed';

	/** Code de double authentification refusé à la connexion. */
	case TwoFactorChallengeFailed = 'auth.two_factor_failed';

	/** Connexion avec un code de secours à usage unique. */
	case RecoveryCodeUsed = 'auth.recovery_code_used';

	/** Déconnexion volontaire. */
	case Logout = 'auth.logout';

	/** Compte verrouillé après des échecs répétés (13.03). */
	case AccountLocked = 'auth.account_locked';

	/** Double authentification activée par l'utilisateur. */
	case TwoFactorEnabled = 'auth.two_factor_enabled';

	/** Double authentification désactivée par l'utilisateur. */
	case TwoFactorDisabled = 'auth.two_factor_disabled';

	/** Nouveaux codes de secours tirés (les anciens sont invalidés). */
	case RecoveryCodesRegenerated = 'auth.recovery_codes_regenerated';

	// Cycle de vie des comptes (11.06 ; invitation et suivants : identite-7).

	/** Compte créé (administrateur d'instance par la commande d'installation). */
	case AccountCreated = 'account.created';

	/** Invitation envoyée. */
	case AccountInvited = 'account.invited';

	/** Compte activé par son titulaire. */
	case AccountActivated = 'account.activated';

	/** Compte désactivé. */
	case AccountDeactivated = 'account.deactivated';

	/** Compte réactivé. */
	case AccountReactivated = 'account.reactivated';

	/** Compte supprimé (pseudonymisé). */
	case AccountDeleted = 'account.deleted';

	/** Compte déverrouillé par un administrateur avant l'échéance. */
	case AccountUnlocked = 'account.unlocked';

	/** Profil modifié par son titulaire. */
	case ProfileUpdated = 'account.profile_updated';

	// Organisations et droits.

	/** Organisation créée. */
	case TenantCreated = 'tenant.created';

	/** Fiche d'organisation modifiée. */
	case TenantUpdated = 'tenant.updated';

	/** Organisation supprimée. */
	case TenantDeleted = 'tenant.deleted';

	/** Rattachement, rôle ou droit d'accès modifié (ACL). */
	case AclChanged = 'acl.changed';

	// Tests, vulnérabilités, remédiations.

	/** Test créé. */
	case TestCreated = 'test.created';

	/** Fiche de test modifiée. */
	case TestUpdated = 'test.updated';

	/** Statut d'un test changé. */
	case TestStatusChanged = 'test.status_changed';

	/** Test supprimé. */
	case TestDeleted = 'test.deleted';

	/** Vulnérabilité créée. */
	case VulnerabilityCreated = 'vulnerability.created';

	/** Fiche de vulnérabilité modifiée. */
	case VulnerabilityUpdated = 'vulnerability.updated';

	/** Vulnérabilité supprimée. */
	case VulnerabilityDeleted = 'vulnerability.deleted';

	/** Statut d'une remédiation changé. */
	case RemediationStatusChanged = 'remediation.status_changed';

	/** Rapport validé par le client. */
	case ClientValidated = 'test.client_validated';

	// Documents chiffrés et exports.

	/** Document partagé avec un utilisateur. */
	case DocumentShared = 'document.shared';

	/** Partage d'un document révoqué. */
	case DocumentRevoked = 'document.revoked';

	/** Propriété d'un document transférée. */
	case OwnershipTransferred = 'document.ownership_transferred';

	/** Rechiffrement demandé au départ d'un utilisateur. */
	case ReencryptionRequested = 'document.reencryption_requested';

	/** Export produit (rapport, CSV, JSON). */
	case ExportGenerated = 'export.generated';

	// Réglages.

	/** Fiche de la société prestataire modifiée. */
	case CompanyProfileUpdated = 'company_profile.updated';
}
