# Story: OpenAPI 3.1, Swagger UI et en-têtes de sécurité

**ID:** socle-6
**Epic:** socle
**Sprint:** 3
**Status:** todo
**Points:** 2

## Context

En tant qu'administrateur, je veux la documentation de l'API et une SPA protégée par une CSP stricte.

## Acceptance Criteria

- [ ] `/api/openapi.json` généré et `/api/documentation` réservé aux administrateurs, désactivable, avec sa propre CSP à nonce
- [ ] CSP de 13.04 sur toutes les réponses de la SPA (Trusted Types, rapports vers `/api/v1/csp-reports`) et en-têtes de l'API ; HSTS envoyé par l'application, CORS désactivé (liste d'origines configurable), cookie de session `__Host-` `Secure`/`HttpOnly`/`SameSite=Strict` ([[13.06 Infrastructure, e-mail et sécurité HTTP]])
- [ ] Tests E2E sous Firefox et Chromium : aucune violation CSP inattendue, charges XSS affichées comme texte, rapports de violation reçus en HTTPS

## Technical Notes

Dépend de socle-4, decisions-4 et decisions-7. Politique, en-têtes et preuve de faisabilité : [[13.04 Protection de la clé privée contre le XSS]].

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
