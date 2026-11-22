# Story: Rôles, ACL et isolation multi-tenant

**ID:** identite-5
**Epic:** identite
**Sprint:** 4
**Status:** done
**Points:** 5

## Context

En tant que client final, je veux ne voir que les données de mon organisation.

## Acceptance Criteria

- [x] Rôles root, administrateur, chef de projet, testeur, client final
- [x] Chaque requête filtrée par tenant ; un objet hors tenant renvoie 404
- [x] Tests d'intégration prouvant l'absence de fuite inter-tenant

## Technical Notes

Dépend de identite-1.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
