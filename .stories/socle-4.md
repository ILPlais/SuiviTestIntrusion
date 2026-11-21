# Story: Enveloppe d'erreurs et pagination API v1

**ID:** socle-4
**Epic:** socle
**Sprint:** 2
**Status:** in-progress
**Points:** 3

## Context

En tant que client de l'API, je veux des réponses uniformes afin de traiter erreurs et listes de la même façon.

## Acceptance Criteria

- [ ] Format `{code, message, details}` pour toutes les erreurs, avec la table HTTP du CDC
- [ ] Messages issus des fichiers de langue Laravel (français en V1), jamais de trace technique
- [ ] Pagination curseur (défaut) et page, `limit` ≤ 200, réponse `data` + `meta`
- [ ] Tri et filtres limités à une liste blanche

## Technical Notes

Dépend de socle-1.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
