# Story: CI et outillage de tests

**ID:** socle-3
**Epic:** socle
**Sprint:** 2
**Status:** todo
**Points:** 3

## Context

En tant que développeur, je veux une CI qui bloque les régressions.

## Acceptance Criteria

- [ ] Pest/PHPUnit, Vitest et Playwright exécutés en CI
- [ ] Lint PHP et JS, analyse statique
- [ ] Matrice de tests sur les trois SGBD
- [ ] Règles ESLint de 13.04 bloquantes : `vue/no-v-html`, `no-eval`, `no-implied-eval`, `no-new-func`, écritures HTML hors du module de rendu

## Technical Notes

Dépend de socle-1.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
