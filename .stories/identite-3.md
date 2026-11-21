# Story: Double authentification TOTP

**ID:** identite-3
**Epic:** identite
**Sprint:** 3
**Status:** done
**Points:** 2

## Context

En tant qu'utilisateur, je veux protéger mon compte par un second facteur.

## Acceptance Criteria

- [x] TOTP compatible Google Authenticator
- [x] Codes de secours
- [x] Activation obligatoire possible par politique d'instance

## Technical Notes

Dépend de identite-1. Aucun e-mail en V1 ([[13.06 Infrastructure, e-mail et sécurité HTTP]]) : le code par e-mail relève de exploitation-6 (V2).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
