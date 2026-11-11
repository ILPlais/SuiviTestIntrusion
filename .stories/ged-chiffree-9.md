# Story: Départ d'un utilisateur : trousseaux et propriété

**ID:** ged-chiffree-9
**Epic:** ged-chiffree
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que chef de projet, je veux qu'un départ coupe l'accès aux contenus chiffrés sans faire perdre de preuve (12.01 Rapport d'analyse du CDC §2.6).

## Acceptance Criteria

- [ ] Suppression d'un compte : toutes ses entrées de trousseau retirées côté serveur, sans navigateur (révocation douce)
- [ ] Tests ouverts (Planifié, En cours, Terminé) : demande de rechiffrement groupée par test, adressée au propriétaire puis aux autres détenteurs, avancement suivi, relances ; tests validés : révocation douce seule, limite affichée
- [ ] Propriétaire de chaque contenu : transfert manuel vers un détenteur, transfert automatique au chef de projet à la désactivation
- [ ] Contrôle du dernier détenteur avant suppression : liste, confirmation explicite, proposition de purge ; liste « détenteur unique » pour le chef de projet
- [ ] Désaffectation d'un test ou retrait d'un tenant : mêmes règles, limitées au test ou au tenant
- [ ] Tests multi-utilisateurs reprenant les constats de la preuve de 13.05

## Technical Notes

Dépend de ged-chiffree-6 et identite-7. Règles : [[13.05 Trousseau d'équipe et départ d'un utilisateur]] et 11.11, « Départ d'un utilisateur ». S'applique aux documents GED, aux exécutions d'outils (deroulement-6) et aux secrets du périmètre (engagement-4).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
