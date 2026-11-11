# Story: Cycle de vie des utilisateurs

**ID:** identite-7
**Epic:** identite
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant qu'administrateur, je veux inviter, désactiver, réactiver et supprimer des utilisateurs (12.01 Rapport d'analyse du CDC §2.6).

## Acceptance Criteria

- [ ] Invitation par un administrateur (tout rôle) ou par un chef de projet (clients finaux de ses tenants) ; aucune inscription libre
- [ ] Lien à usage unique valable 72 h (paramétrable), condensat seul stocké, renvoi qui invalide le lien précédent, annulation
- [ ] Compte existant rattaché au tenant sans nouvel enrôlement ; invitation refusée pour un compte désactivé ; lien affiché à l'invitant si l'e-mail est désactivé
- [ ] Compte root créé à l'installation par une commande `artisan` qui émet un lien d'enrôlement
- [ ] Désactivation réversible : connexion refusée, sessions et jetons API révoqués, invitations émises annulées, tâches sans autre assigné signalées au chef de projet
- [ ] Suppression seulement après désactivation ; dernier administrateur actif protégé ; données d'authentification et copies de clé effacées ; compte pseudonymisé
- [ ] Événements audités et notifications du CDC (11.06, 11.02)

## Technical Notes

Dépend de identite-5, identite-6 et ged-chiffree-1. Règles : 11.06, « Cycle de vie des comptes », et [[13.05 Trousseau d'équipe et départ d'un utilisateur]]. Le volet trousseaux (entrées, rechiffrement, propriété) relève de ged-chiffree-9.

Enrôlement avec clé de récupération, mot de passe oublié et réinitialisation du coffre validée par un administrateur : [[13.03 Authentification locale et coffre de clés]].

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
