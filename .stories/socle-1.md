# Story: Squelette Laravel API + SPA Vue 3

**ID:** socle-1
**Epic:** socle
**Sprint:** 2
**Status:** done
**Points:** 3

## Context

En tant que développeur, je veux un dépôt applicatif initialisé afin de livrer les fonctionnalités par incréments.

## Acceptance Criteria

- [x] Projet Laravel 11+ sur PHP 8.3+ exposant `/api/v1/health`
- [x] SPA Vue 3 + Vite + Pinia + vue-i18n appelant l'API ; aucune chaîne en dur (français en V1)
- [x] Dates échangées en UTC (ISO 8601), affichées avec `Intl` dans le fuseau du navigateur ([[13.07 Licence, langues et accessibilité]])
- [x] SQLite, MySQL/MariaDB et PostgreSQL configurables via `.env`

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
