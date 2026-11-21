# Story: Docker Compose et installation manuelle

**ID:** socle-2
**Epic:** socle
**Sprint:** 3
**Status:** in-progress
**Points:** 3

## Context

En tant qu'exploitant, je veux déployer l'application en Docker ou à la main.

## Acceptance Criteria

- [ ] Services `app` (HTTP, réseau interne), `queue` (`queue:work --tries=3 --backoff=10,60,300`), `scheduler` (`schedule:work`), `database` optionnelle, volume des preuves
- [ ] Exemple de configuration d'un proxy inverse externe (TLS 1.2 minimum, 1.3 privilégié, en-têtes `X-Forwarded-*`, corps de 110 Mio) et `TRUSTED_PROXIES`
- [ ] Documentation pas-à-pas de l'installation manuelle (PHP-FPM, queue, cron, permissions)

## Technical Notes

Dépend de socle-1 et decisions-4. Infrastructure : [[13.06 Infrastructure, e-mail et sécurité HTTP]] ; le service web HTTPS intégré relève de socle-7 (V2).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
