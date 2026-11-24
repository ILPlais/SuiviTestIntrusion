# Story: Fiche vulnérabilité

**ID:** vulnerabilites-3
**Epic:** vulnerabilites
**Sprint:** 5
**Status:** done
**Points:** 3

## Context

En tant que testeur, je veux documenter un constat.

## Acceptance Criteria

- [x] Référence, CVE/CWE, description, phase, composants concernés
- [x] Score CVSS et niveau (Aucun → Critique)
- [x] Version CVSS et vecteur brut enregistrés avec le score, pour accueillir CVSS v4.0 en V2 sans migration
- [x] Liens vers objets du périmètre (machines, applications, ressources) et exécutions d'outils (deroulement-6)

## Technical Notes

Dépend de engagement-4 et vulnerabilites-1.

À la livraison : les composants concernés sont des objets du périmètre du même test (les applications et ressources d’engagement-7 en seront aussi) ; le lien vers les exécutions d’outils viendra avec deroulement-6.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
