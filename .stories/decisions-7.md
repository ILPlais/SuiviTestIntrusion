# Story: ADR : protection de la clé privée contre le XSS

**ID:** decisions-7
**Epic:** decisions
**Sprint:** 1
**Status:** done
**Points:** 3

## Context

En tant qu'architecte, je veux protéger la clé privée déchiffrée contre un XSS (12.01 Rapport d'analyse du CDC §4.4, et la CSP du §3.2).

## Acceptance Criteria

- [x] Politique CSP fixée : SPA, en-têtes de l'API, page Swagger UI
- [x] Clé privée isolée dans un Web Worker dédié : opérations exposées, données qui n'en sortent jamais
- [x] Règles de rendu des contenus hostiles : Vue, Trusted Types, PrismJS, Mermaid, aperçus des preuves, export HTML
- [x] Limites résiduelles documentées
- [x] Preuve de faisabilité dans un navigateur consignée dans l'ADR

## Technical Notes

Dépend de decisions-1, decisions-3.

Décision : [[13.04 Protection de la clé privée contre le XSS]] (CSP par liste blanche `'self'` avec Trusted Types, clé privée et CEK dans un Web Worker dédié, contenus hostiles assainis par DOMPurify, aperçus limités aux images matricielles et au texte). Pas de code pour un ADR : une preuve de faisabilité (mini SPA Vite + Vue sous Firefox 159 et Chrome 157, cas passants, attaques bloquées, variantes de contrôle) tient lieu de test, résultats consignés dans l'ADR.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
