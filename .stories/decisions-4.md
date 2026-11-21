# Story: ADR : infrastructure (file d'attente, e-mail, sécurité HTTP)

**ID:** decisions-4
**Epic:** decisions
**Sprint:** 2
**Status:** done
**Points:** 2

## Context

En tant qu'exploitant, je veux connaître les choix d'infrastructure (12.01 Rapport d'analyse du CDC §3.2–3.5).

## Acceptance Criteria

- [x] Driver de file choisi (Redis ou database) avec stratégie de retry / échec
- [x] Configuration e-mail (driver, désactivation possible hors ligne)
- [x] CORS, TLS/HSTS et flags de cookies Sanctum (la CSP relève de 13.04)
- [x] Taille maximale GED par défaut fixée

## Technical Notes

Décision : [[13.06 Infrastructure, e-mail et sécurité HTTP]] (file `database`, aucun e-mail en V1, TLS par un proxy externe, CORS désactivé, cookies `SameSite=Strict`, 100 Mio par fichier).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
