# Story: Références MITRE ATT&CK sur tâches et vulnérabilités

**ID:** vulnerabilites-7
**Epic:** vulnerabilites
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant que testeur, je veux rattacher mes tâches et mes constats aux techniques MITRE ATT&CK pour qualifier ce qui a été tenté ou trouvé (11.13, « Méthodologies › Référentiel de techniques »).

## Acceptance Criteria

- [ ] Référentiel ATT&CK importé (tactiques, techniques, sous-techniques) avec sa version
- [ ] Une tâche et une vulnérabilité peuvent référencer un ou plusieurs identifiants `Txxxx` ou `Txxxx.yyy`, avec recherche par identifiant ou libellé
- [ ] La version d'ATT&CK utilisée est enregistrée avec le test
- [ ] Les techniques référencées apparaissent dans le rapport technique et dans la description des contre-mesures

## Technical Notes

Dépend de deroulement-1 et vulnerabilites-3. ATT&CK n'est pas une méthodologie : il reste hors du catalogue de engagement-6. Mise à jour du référentiel sans casser les références des tests existants (identifiants dépréciés conservés).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
