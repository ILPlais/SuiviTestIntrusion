# Story: Cycle de vie de la clé en mémoire

**ID:** ged-chiffree-2
**Epic:** ged-chiffree
**Sprint:** 6
**Status:** in-progress
**Points:** 2

## Context

En tant qu'utilisateur, je veux que ma clé déchiffrée ne traîne pas en mémoire (12.01 Rapport d'analyse du CDC §4.3).

## Acceptance Criteria

- [ ] Effacement après inactivité configurable
- [ ] Verrouillage à la mise en veille de l'onglet
- [ ] Limites documentées dans l'aide

## Technical Notes

Dépend de ged-chiffree-1.

Verrouiller revient à arrêter le Worker crypto (`terminate()`) ; un nouveau Worker reste verrouillé jusqu'à la ressaisie du mot de passe ([[13.04 Protection de la clé privée contre le XSS]]).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
