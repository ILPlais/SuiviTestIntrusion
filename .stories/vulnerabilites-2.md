# Story: Moteur CVSS v4.0

**ID:** vulnerabilites-2
**Epic:** vulnerabilites
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que testeur, je veux pouvoir évaluer une vulnérabilité en CVSS v4.0.

## Acceptance Criteria

- [ ] Calcul officiel FIRST v4.0, sans réutiliser les équations v3.1
- [ ] Choix de version par fiche ou par politique d'instance
- [ ] Tests sur les vecteurs de référence FIRST

## Technical Notes

Dépend de vulnerabilites-1.

Reportée en V2 (décision du 2026-11-21, [[22.01 Planification du développement]]) : en V1, seul le moteur v3.1 est livré ; la fiche enregistre déjà la version et le vecteur (vulnerabilites-3), si bien que la v4.0 s'ajoutera sans migration ([[11.10 Cotation CVSS]]).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
