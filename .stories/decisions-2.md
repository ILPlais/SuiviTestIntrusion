# Story: ADR : dérivation de clé (KDF) et protection de la clé privée

**ID:** decisions-2
**Epic:** decisions
**Sprint:** 1
**Status:** todo
**Points:** 2

## Context

En tant qu'architecte, je veux spécifier la KDF et ses paramètres afin de protéger correctement la clé privée (12.01 Rapport d'analyse du CDC §1.3).

## Acceptance Criteria

- [ ] Algorithme choisi (Argon2id recommandé) et bibliothèque navigateur identifiée
- [ ] Paramètres mémoire / itérations / parallélisme et format du sel documentés
- [ ] Procédure de rotation du mot de passe décrite (rechiffrement de la clé privée seule)

## Technical Notes

Dépend de decisions-1.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
