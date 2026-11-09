# Story: Attribution des tâches et verrou optimiste

**ID:** deroulement-2
**Epic:** deroulement
**Sprint:** unassigned
**Status:** todo
**Points:** 2

## Context

En tant que testeur, je veux m'attribuer une tâche libre sans écraser le travail d'un collègue.

## Acceptance Criteria

- [ ] Attribution par le chef de projet, un ou plusieurs assignés par tâche
- [ ] Auto-attribution d'une tâche libre (sans assigné)
- [ ] Conflit 409 si la ressource a changé depuis le chargement, avec avertissement dans l'interface

## Technical Notes

Dépend de deroulement-1. Règles : 11.01, Objectifs › Tâches et Collaboration.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
