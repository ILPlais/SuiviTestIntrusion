# Story: Moteur CVSS v3.1

**ID:** vulnerabilites-1
**Epic:** vulnerabilites
**Sprint:** 5
**Status:** todo
**Points:** 3

## Context

En tant que testeur, je veux un score de base CVSS v3.1 exact.

## Acceptance Criteria

- [ ] Calcul conforme aux formules et coefficients du CDC (arrondi supérieur au dixième)
- [ ] Parsing et génération du vecteur `CVSS:3.1/AV:…`
- [ ] Tests unitaires sur les vecteurs de référence FIRST

## Technical Notes

Logique pure, idéale pour démarrer en TDD.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
