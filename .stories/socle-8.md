# Story: Ergonomie de la SPA : WebTUI, indicateurs de chargement, icônes Nerd Fonts

**ID:** socle-8
**Epic:** socle
**Sprint:** 4
**Status:** in-progress
**Points:** 3

## Context

En tant qu'utilisateur, je veux une interface lisible et réactive, qui signale ce qu'elle est en train de faire.

## Acceptance Criteria

- [ ] Style de l'interface par WebTUI (`@webtui/css`), thème Catppuccin Mocha ou Latte selon la préférence du système
- [ ] Chargements en cours signalés visuellement (throbber WebTUI) : requêtes en cours dans l'en-tête, listes et fiches, boutons pendant l'envoi
- [ ] Icônes Nerd Fonts (`@webtui/plugin-nf`, police Symbols Nerd Font auto-hébergée) dans la navigation et les actions, décoratives pour les lecteurs d'écran
- [ ] Accessibilité conservée : contrôles axe sans violation, cibles de 24 px, contrastes du thème

## Technical Notes

Demandée par l'utilisateur le 2026-11-22, avant engagement-4. Décisions : licence SIL OFL 1.1 acceptée pour la police d'icônes (glyphes Font Awesome), ajoutée à la politique de licences et à 13.07 ; thème Catppuccin automatique (`prefers-color-scheme`). Police auto-hébergée (CSP `font-src 'self'`).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
