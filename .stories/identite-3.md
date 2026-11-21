# Story: Double authentification TOTP

**ID:** identite-3
**Epic:** identite
**Sprint:** 3
**Status:** in-progress
**Points:** 2

## Context

En tant qu'utilisateur, je veux protéger mon compte par un second facteur.

## Acceptance Criteria

- [ ] TOTP compatible Google Authenticator
- [ ] Codes de secours
- [ ] Activation obligatoire possible par politique d'instance

## Technical Notes

Dépend de identite-1. Aucun e-mail en V1 ([[13.06 Infrastructure, e-mail et sécurité HTTP]]) : le code par e-mail relève de exploitation-6 (V2).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
