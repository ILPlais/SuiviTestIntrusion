# Story: Ergonomie de la SPA : WebTUI, indicateurs de chargement, icônes Nerd Fonts

**ID:** socle-8
**Epic:** socle
**Sprint:** 4
**Status:** review
**Points:** 3

## Context

En tant qu'utilisateur, je veux une interface lisible et réactive, qui signale ce qu'elle est en train de faire.

## Acceptance Criteria

- [x] Style de l'interface par WebTUI (`@webtui/css`), thème Catppuccin Mocha ou Latte selon la préférence du système
- [x] Chargements en cours signalés visuellement (throbber WebTUI) : requêtes en cours dans l'en-tête, listes et fiches, boutons pendant l'envoi
- [x] Icônes Nerd Fonts (police Symbols Nerd Font auto-hébergée, réduite aux glyphes employés) dans la navigation et les actions, décoratives pour les lecteurs d'écran
- [x] Accessibilité conservée : contrôles axe sans violation, cibles de 24 px, contrastes du thème

## Technical Notes

Demandée par l’utilisateur le 2026-11-22, avant engagement-4. Thème Catppuccin automatique (`prefers-color-scheme`) ; couleurs de Latte sous 4,5:1 remplacées (titres, liens, code). Police d’icônes : l’OFL avait été acceptée, mais la police complète Symbols Nerd Font mêle aussi CC BY 4.0 et des glyphes sans licence ; seul un sous-ensemble de glyphes Material Design (Apache-2.0, 31 glyphes, 2,9 Ko) est embarqué, auto-hébergé (CSP `font-src 'self'`, le plugin `@webtui/plugin-nf` chargeant depuis un CDN), dans la liste autorisée sans exception (13.07). Captures dans [[43.01 Aperçu de l'interface]] (`npm run captures`).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [ ] Code merged to main
