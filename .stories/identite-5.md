# Story: Rôles, ACL et isolation multi-tenant

**ID:** identite-5
**Epic:** identite
**Sprint:** 4
**Status:** todo
**Points:** 5

## Context

En tant que client final, je veux ne voir que les données de mon organisation.

## Acceptance Criteria

- [ ] Rôles root, administrateur, chef de projet, testeur, client final
- [ ] Chaque requête filtrée par tenant ; un objet hors tenant renvoie 404
- [ ] Tests d'intégration prouvant l'absence de fuite inter-tenant

## Technical Notes

Dépend de identite-1.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
