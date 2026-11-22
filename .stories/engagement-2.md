# Story: Création d'un test et statuts

**ID:** engagement-2
**Epic:** engagement
**Sprint:** 4
**Status:** review
**Points:** 3

## Context

En tant que chef de projet, je veux créer un test rattaché à un client.

## Acceptance Criteria

- [x] Test avec type (RedTeam, Black/Grey/WhiteBox) et interne/externe
- [x] Statuts Planifié → En cours → Terminé → Validé, transitions auditées

## Technical Notes

Dépend de engagement-1.

Décisions du 2026-11-22 : transitions **libres** entre les quatre statuts, chacune tracée dans la table d’historique `test_status_changes` (ancien et nouveau statut, auteur, date, commentaire facultatif), affichée sur la fiche du test et reprise plus tard par le journal d’audit (identite-6). Création, modification et changement de statut : chef de projet de l’organisation et administrateur d’instance ; suppression : administrateur ; testeurs et clients finaux en lecture. Colonnes `type` et `exposure` ajoutées à `tests` dans 14.01 ; les cinq phases fixes sont créées avec le test. Modèle Eloquent `PenetrationTest` (table `tests`).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [ ] Code merged to main
