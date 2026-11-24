# Story: Journal d'audit

**ID:** identite-6
**Epic:** identite
**Sprint:** 6
**Status:** review
**Points:** 3

## Context

En tant qu'administrateur, je veux savoir qui a fait quoi et quand.

## Acceptance Criteria

- [x] Auteur, horodatage, objet, action, avant/après
- [x] Événements minimaux du CDC (connexions, ACL, partages, exports, statuts, validation client)
- [x] Aucun secret en clair, rétention paramétrable

## Technical Notes

Dépend de identite-5.

Réalisé : table `audit_events` (sans clé étrangère vers `tenants`, pour survivre à la suppression d’une organisation), service `App\Audit\AuditLog` (masquage des champs secrets à toute profondeur, textes tronqués à 500 caractères), événements inaltérables, purge quotidienne `model:prune` selon `AUDIT_RETENTION_DAYS` (365 jours par défaut, 0 = sans purge), API `GET /api/v1/audit-events` (administrateurs d’instance, filtres action, auteur, organisation, objet).

Le catalogue `App\Enums\AuditAction` réserve déjà les événements du CDC dont la fonctionnalité n’existe pas encore ; chaque story les émet en l’apportant : comptes et ACL (identite-7), partages, révocations, transferts et rechiffrement (ged-chiffree-*), exports (exports-*), validation client. Pas d’écran de consultation : aucun n’est maquetté en V1 (15.01).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [ ] Code merged to main
