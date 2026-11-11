# Story: Moteur de rapport et modèles par défaut

**ID:** exports-1
**Epic:** exports
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que chef de projet, je veux générer un rapport à partir des sections choisies.

## Acceptance Criteria

- [ ] Modèles Exécutif, Technique, Annexes preuves, combinables
- [ ] Sélection atomique des sections
- [ ] Génération en file d'attente

## Technical Notes

Dépend de resultats-3. Le modèle Technique liste les tâches avec leur résultat ; les tâches abandonnées y figurent comme non testées, avec leur motif (11.04 ; 11.08, Tâches › Résultat).

Les exécutions d'outils y figurent par leurs métadonnées (outil, version, dates, cibles, liens), sans commande ni sortie, qui sont chiffrées (11.04 ; 11.08, Outils).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
