# Story: Moteur CVSS v3.1

**ID:** vulnerabilites-1
**Epic:** vulnerabilites
**Sprint:** 5
**Status:** done
**Points:** 3

## Context

En tant que testeur, je veux un score de base CVSS v3.1 exact.

## Acceptance Criteria

- [x] Calcul conforme aux formules et coefficients du CDC (arrondi supérieur au dixième)
- [x] Parsing et génération du vecteur `CVSS:3.1/AV:…`
- [x] Tests unitaires sur les vecteurs de référence FIRST

## Technical Notes

Logique pure, idéale pour démarrer en TDD.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
