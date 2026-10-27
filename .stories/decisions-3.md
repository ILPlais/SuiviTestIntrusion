# Story: ADR : authentification LDAP/AD et coffre de clés

**ID:** decisions-3
**Epic:** decisions
**Sprint:** 1
**Status:** todo
**Points:** 3

## Context

En tant qu'architecte, je veux concilier LDAP/AD avec la clé privée dérivée du mot de passe (12.01 Rapport d'analyse du CDC §1.2).

## Acceptance Criteria

- [ ] Choix documenté : passphrase de coffre distincte, clé de récupération, ou autre
- [ ] Parcours d'enrôlement et de récupération décrit
- [ ] Politique de mot de passe local et verrouillage de compte fixés (§4.1, §4.2)

## Technical Notes

Dépend de decisions-2. Bloquant pour identite.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
