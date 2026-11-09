# Story: Règle d'agrégation pour un test mixte v3.1 / v4.0

**ID:** vulnerabilites-6
**Epic:** vulnerabilites
**Sprint:** unassigned
**Status:** todo
**Points:** 1

## Context

En tant que chef de projet, je veux un score global sans comparaison abusive (12.01 Rapport d'analyse du CDC §1.4).

## Acceptance Criteria

- [x] Règle documentée dans le CDC (11.12, Risque global › Test mixte v3.1 / v4.0)
- [ ] Badge « Test mixte » sur le bandeau du tableau de bord et dans la synthèse des rapports exportés
- [ ] Bandeau : niveau le plus élevé, puis maximum de chaque version avec son niveau
- [ ] Moyenne pondérée calculée par version, avec le nombre de fiches ; aucun calcul ni tri croisé entre v3.1 et v4.0

## Technical Notes

Dépend de vulnerabilites-2. Règle : 11.12, Risque global › Test mixte v3.1 / v4.0 ; seuls les niveaux (Aucun → Critique) se comparent entre versions.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
