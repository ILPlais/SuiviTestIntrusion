# Story: Risque global d'un test

**ID:** resultats-1
**Epic:** resultats
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant que chef de projet, je veux voir la gravité globale d'un test.

## Acceptance Criteria

- [ ] Maximum CVSS des vulnérabilités ouvertes et niveau associé (par version si test mixte, cf. vulnerabilites-6)
- [ ] Score moyen pondéré P1=4…P4=1, informatif (par version si test mixte, cf. vulnerabilites-6)
- [ ] Profil d'impact business par axe
- [ ] Compteurs par sévérité, phase, statut de remédiation, priorité

## Technical Notes

Dépend de vulnerabilites-5 et vulnerabilites-6.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
