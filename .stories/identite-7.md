# Story: Cycle de vie des utilisateurs

**ID:** identite-7
**Epic:** identite
**Sprint:** 6
**Status:** done
**Points:** 5

## Context

En tant qu'administrateur, je veux inviter, désactiver, réactiver et supprimer des utilisateurs (12.01 Rapport d'analyse du CDC §2.6).

## Acceptance Criteria

- [x] Invitation par un administrateur (tout rôle) ou par un chef de projet (clients finaux de ses tenants) ; aucune inscription libre
- [x] Lien à usage unique valable 72 h (paramétrable), condensat seul stocké, renvoi qui invalide le lien précédent, annulation
- [x] Compte existant rattaché au tenant sans nouvel enrôlement ; invitation refusée pour un compte désactivé ; lien toujours affiché à l'invitant (aucun e-mail en V1)
- [x] Mot de passe oublié : lien de réinitialisation à usage unique, valable 24 h, émis par un administrateur et affiché chez lui ; verrouillage signalé dans l'application ([[13.06 Infrastructure, e-mail et sécurité HTTP]])
- [x] Compte root créé à l'installation par une commande `artisan` qui émet un lien d'enrôlement
- [x] Désactivation réversible : connexion refusée, sessions et jetons API révoqués, invitations émises annulées, tâches sans autre assigné signalées au chef de projet
- [x] Suppression seulement après désactivation ; dernier administrateur actif protégé ; données d'authentification et copies de clé effacées ; compte pseudonymisé
- [x] Événements audités et notifications du CDC (11.06, 11.02)

## Technical Notes

Dépend de identite-5, identite-6 et ged-chiffree-1. Règles : 11.06, « Cycle de vie des comptes », et [[13.05 Trousseau d'équipe et départ d'un utilisateur]]. Le volet trousseaux (entrées, rechiffrement, propriété) relève de ged-chiffree-9.

Enrôlement avec clé de récupération, mot de passe oublié et réinitialisation du coffre validée par un administrateur : [[13.03 Authentification locale et coffre de clés]].

Réalisé : table `invitations` (organisation, rôle ou administrateur d'instance, invitant, échéance), lien porté par le compte invité (`activation_token_hash`, 72 h par défaut, `AUTH_ACTIVATION_HOURS`) ; un chef de projet ne peut pas reprendre le lien d'un compte invité par un autre (il recevrait le rôle promis). Colonnes `users.deactivated_at`, `pseudonymized_at`, `reset_token_hash`, `reset_expires_at` ; middleware `active` qui ferme la session d'un compte désactivé ; sessions en base et jetons API supprimés à la désactivation. Suppression : ligne pseudonymisée (« Utilisateur supprimé n° X », adresse `deleted-<id>@deleted.invalid`), secrets, second facteur, clés et rattachements effacés, empreinte conservée dans l'audit. Un administrateur ne peut se désactiver ni se supprimer lui-même, ce qui garde toujours un administrateur actif. Mot de passe oublié : lien de 24 h (`AUTH_RESET_HOURS`), copie 2 livrée après le second facteur et marquée en session (15 min), même paire de clés exigée. API `/api/v1/invitations`, `/api/v1/users`, `/api/v1/auth/password-reset` ; écran Utilisateurs (onglets Utilisateurs et Invitations) et page `/reinitialisation/:token`.

Reporté, faute des objets nécessaires : notifications in-app (invitation, rattachement, désactivation) → exploitation-1 (centre de notifications) ; tâches dont le compte désactivé est le seul assigné → deroulement-2 (attribution des tâches) ; liste des contenus dont il est seul détenteur et transfert de propriété → ged-chiffree-9 (le dialogue de suppression indique pour l'instant qu'il n'y en a aucun). Réinitialisation du coffre validée par un administrateur (mot de passe et clé de récupération perdus) : non traitée, à prévoir avec ged-chiffree-9 (trousseaux à supprimer). Les événements sont audités : `account.invited`, `account.invitation_cancelled`, `account.deactivated`, `account.reactivated`, `account.deleted`, `account.unlocked`, `auth.password_reset_issued`, `auth.password_reset`.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
