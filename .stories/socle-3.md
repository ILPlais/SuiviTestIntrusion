# Story: CI et outillage de tests

**ID:** socle-3
**Epic:** socle
**Sprint:** 2
**Status:** done
**Points:** 3

## Context

En tant que développeur, je veux une CI qui bloque les régressions.

## Acceptance Criteria

- [x] Pest/PHPUnit, Vitest et Playwright exécutés en CI
- [x] Lint PHP et JS, analyse statique
- [x] Matrice de tests sur les trois SGBD
- [x] Contrôle des licences des dépendances PHP et JS (liste autorisée de [[13.07 Licence, langues et accessibilité]]) et fichier de mentions généré
- [x] Contrôles d'accessibilité automatiques (axe) disponibles dans les tests Playwright
- [x] Règles ESLint de 13.04 bloquantes : `vue/no-v-html`, `no-eval`, `no-implied-eval`, `no-new-func`, écritures HTML hors du module de rendu

## Technical Notes

Dépend de socle-1.

Réalisé : PHPUnit (Pest non retenu), PHP-CS-Fixer en tabulations, Larastan niveau 6, ESLint (13.04, 13.07, tabulations), Vitest, Playwright et axe, contrôle des licences (scripts/licenses/), workflow .github/workflows/ci.yml.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
