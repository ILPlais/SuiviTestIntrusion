# Story: Périmètre réseau

**ID:** engagement-4
**Epic:** engagement
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant que testeur, je veux déclarer machines, services et comptes du périmètre.

## Acceptance Criteria

- [ ] Machines (IP, noms), services, utilisateurs
- [ ] Exclusions et plages horaires interdites
- [ ] Objets du périmètre communs (inclus/exclu, commentaire, plages interdites, liens vers tâches et vulnérabilités), réutilisés par engagement-5 et engagement-7

## Technical Notes

Dépend de engagement-2. Les secrets (mots de passe, identifiants découverts) sont chiffrés dans le navigateur comme les preuves (13.01). Champs : CDC « Périmètre › Objets du périmètre » et « Périmètre › Réseau ».

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
