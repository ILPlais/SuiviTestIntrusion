# Story: Formats PDF, OpenDocument et HTML

**ID:** exports-2
**Epic:** exports
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que client, je veux recevoir le rapport dans le format attendu.

## Acceptance Criteria

- [ ] PDF, ODT et HTML avec graphiques
- [ ] Classe de rate limiting Export appliquée, export audité
- [ ] Rapport HTML : contenu saisi échappé, CSP en `<meta>` qui interdit tout script (13.04)

## Technical Notes

Dépend de exports-1. Moteur PDF sans licence GPL ni AGPL liée au code ([[13.07 Licence, langues et accessibilité]]).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
