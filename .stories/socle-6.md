# Story: OpenAPI 3.1, Swagger UI et en-têtes de sécurité

**ID:** socle-6
**Epic:** socle
**Sprint:** unassigned
**Status:** todo
**Points:** 2

## Context

En tant qu'administrateur, je veux la documentation de l'API et une SPA protégée par une CSP stricte.

## Acceptance Criteria

- [ ] `/api/openapi.json` généré et `/api/documentation` réservé aux administrateurs, désactivable
- [ ] CSP sans `unsafe-inline`, HSTS, CORS restreint, cookies `Secure`/`HttpOnly`/`SameSite`

## Technical Notes

Dépend de socle-4 et decisions-4.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
