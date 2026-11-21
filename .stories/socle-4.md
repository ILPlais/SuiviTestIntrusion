# Story: Enveloppe d'erreurs et pagination API v1

**ID:** socle-4
**Epic:** socle
**Sprint:** 2
**Status:** done
**Points:** 3

## Context

En tant que client de l'API, je veux des réponses uniformes afin de traiter erreurs et listes de la même façon.

## Acceptance Criteria

- [x] Format `{code, message, details}` pour toutes les erreurs, avec la table HTTP du CDC
- [x] Messages issus des fichiers de langue Laravel (français en V1), jamais de trace technique
- [x] Pagination curseur (défaut) et page, `limit` ≤ 200, réponse `data` + `meta`
- [x] Tri et filtres limités à une liste blanche

## Technical Notes

Dépend de socle-1.

Réalisé : backend/app/Http/Api/ApiErrorRenderer.php (branché dans bootstrap/app.php) et ApiListQuery.php (helper des listes) ; messages dans backend/lang/fr/.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
