# Story: Périmètre réseau

**ID:** engagement-4
**Epic:** engagement
**Sprint:** 4
**Status:** done
**Points:** 3

## Context

En tant que testeur, je veux déclarer machines, services et comptes du périmètre.

## Acceptance Criteria

- [x] Machines (IP, noms), services, utilisateurs
- [x] Exclusions et plages horaires interdites
- [x] Objets du périmètre communs (inclus/exclu, commentaire, plages interdites, liens vers tâches et vulnérabilités), réutilisés par engagement-5 et engagement-7

## Technical Notes

Dépend de engagement-2. Les secrets (mots de passe, identifiants découverts) sont chiffrés dans le navigateur comme les preuves (13.01), pour l'équipe du test (13.05). Champs : 11.07, « Objets du périmètre » et « Réseau ».

À la livraison : les liens vers les tâches et les vulnérabilités viendront avec les stories qui créent ces objets (deroulement-1, vulnerabilites-3) ; un compte de test n’enregistre encore aucun secret, leur chiffrement dans le navigateur attend la GED chiffrée.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
