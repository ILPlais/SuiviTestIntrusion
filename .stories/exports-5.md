# Story: Export et import JSON/XML de migration

**ID:** exports-5
**Epic:** exports
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant qu'administrateur, je veux migrer des données entre instances.

## Acceptance Criteria

- [ ] `schema_version`, entités du CDC, trousseaux toujours chiffrés
- [ ] Blobs en paquet séparé
- [ ] Coffres utilisateurs exclus sauf option explicite
- [ ] Import refusant une version ou un paquet incomplet

## Technical Notes

Dépend de exports-1.

Entités : le catalogue d'outils et les exécutions d'outils en font partie ; le contenu chiffré des exécutions rejoint le paquet des blobs (11.04).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
