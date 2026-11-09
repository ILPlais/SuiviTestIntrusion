# Story: Phases et modèle de tâche

**ID:** deroulement-1
**Epic:** deroulement
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que chef de projet, je veux découper le test en phases et tâches (12.01 Rapport d'analyse du CDC §2.2, §2.8).

## Acceptance Criteria

- [ ] Cinq phases par défaut, désactivables et extensibles
- [x] Modèle de tâche documenté dans le CDC (11.01, Objectifs › Tâches)
- [ ] Tâche : référence, titre, description, phase, assignés, statut et motif, résultat, dates prévues et réelles, temps passé
- [ ] Six statuts (À faire, En cours, Bloquée, Terminée, Vérifiée, Abandonnée) et transitions du CDC ; motif obligatoire pour Bloquée et Abandonnée ; passage à Vérifiée et réouverture d'une tâche vérifiée réservés au chef de projet
- [ ] Résultat (Vulnérable, Non vulnérable, Non applicable, Non concluant) obligatoire pour passer à Terminée ; « Vulnérable » exige au moins une vulnérabilité liée
- [ ] Liens n-n vers objectifs, objets du périmètre, vulnérabilités et exécutions d'outils

## Technical Notes

Dépend de engagement-2. Modèle : 11.01, Objectifs › Tâches. Les références ATT&CK relèvent de vulnerabilites-7, les exécutions d'outils de deroulement-6 ; un lien vers une entité pas encore livrée est ajouté par la story qui la livre (engagement-4/5/7, vulnerabilites-3, deroulement-6).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
