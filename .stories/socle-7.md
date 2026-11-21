# Story: Service web HTTPS intégré à Docker Compose

**ID:** socle-7
**Epic:** socle
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant qu'exploitant, je veux une instance HTTPS complète sans proxy externe.

## Acceptance Criteria

- [ ] Service web dans le Compose qui termine le TLS (1.2 minimum, 1.3 privilégié)
- [ ] Certificats automatiques (ACME) ou fournis par l'exploitant pour une instance interne
- [ ] Le proxy externe reste une option documentée

## Technical Notes

Dépend de socle-2. Règles : [[13.06 Infrastructure, e-mail et sécurité HTTP]], Sécurité HTTP.

Reportée en V2 (décision du 2026-11-21, [[13.06 Infrastructure, e-mail et sécurité HTTP]]) : en V1, un proxy inverse externe fourni par l'exploitant termine le TLS.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
