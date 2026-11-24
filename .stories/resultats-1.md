# Story: Risque global d'un test

**ID:** resultats-1
**Epic:** resultats
**Sprint:** 5
**Status:** done
**Points:** 3

## Context

En tant que chef de projet, je veux voir la gravité globale d'un test.

## Acceptance Criteria

- [x] Maximum CVSS des vulnérabilités ouvertes et niveau associé (par version si test mixte, en V2 : vulnerabilites-6)
- [x] Score moyen pondéré P1=4…P4=1, informatif (par version si test mixte, en V2 : vulnerabilites-6)
- [x] Profil d'impact business par axe
- [x] Compteurs par sévérité, phase, statut de remédiation, priorité

## Technical Notes

Dépend de vulnerabilites-5. Test mixte v3.1 / v4.0 : vulnerabilites-6, en V2.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
