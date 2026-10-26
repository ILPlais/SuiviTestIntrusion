# Story: ADR : infrastructure (file d'attente, e-mail, sécurité HTTP)

**ID:** decisions-4
**Epic:** decisions
**Sprint:** 1
**Status:** todo
**Points:** 2

## Context

En tant qu'exploitant, je veux connaître les choix d'infrastructure (Plan/rapport_cdc.md §3.2–3.5).

## Acceptance Criteria

- [ ] Driver de file choisi (Redis ou database) avec stratégie de retry / échec
- [ ] Configuration e-mail (driver, désactivation possible hors ligne)
- [ ] Politique CSP stricte, CORS, TLS/HSTS et flags de cookies Sanctum
- [ ] Taille maximale GED par défaut fixée

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
