# Story: ADR : trousseau d'équipe et départ d'un utilisateur

**ID:** decisions-8
**Epic:** decisions
**Sprint:** 1
**Status:** done
**Points:** 2

## Context

En tant qu'architecte, je veux qu'un départ coupe l'accès aux preuves sans en faire perdre aucune (12.01 Rapport d'analyse du CDC §2.6).

## Acceptance Criteria

- [x] Détenteurs initiaux des contenus chiffrés d'un test : équipe du test, client final exclu, partage groupé à l'affectation
- [x] Propriétaire d'un contenu et transfert de propriété (manuel, automatique au chef de projet)
- [x] Désactivation et suppression : sort des entrées de trousseau, contrôle du dernier détenteur, pseudonymisation
- [x] Révocation au départ : douce immédiate, forte pour les tests ouverts ; limites documentées
- [x] Preuve de faisabilité openpgp.js consignée dans l'ADR

## Technical Notes

Dépend de decisions-1, decisions-3.

Décision : [[13.05 Trousseau d'équipe et départ d'un utilisateur]]. Pas de code pour un ADR : une preuve de faisabilité (openpgp 6.1.1 sous Node 26, cinq utilisateurs, trois tests, rechiffrement en flux de 100 Mio) tient lieu de test, résultats consignés dans l'ADR. Règles reportées dans 11.06 (Cycle de vie des comptes) et 11.11 (Gestion, Départ d'un utilisateur).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
