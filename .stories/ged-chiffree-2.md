# Story: Cycle de vie de la clé en mémoire

**ID:** ged-chiffree-2
**Epic:** ged-chiffree
**Sprint:** 6
**Status:** done
**Points:** 2

## Context

En tant qu'utilisateur, je veux que ma clé déchiffrée ne traîne pas en mémoire (12.01 Rapport d'analyse du CDC §4.3).

## Acceptance Criteria

- [x] Effacement après inactivité configurable
- [x] Verrouillage à la mise en veille de l'onglet
- [x] Limites documentées dans l'aide

## Technical Notes

Dépend de ged-chiffree-1.

Verrouiller revient à arrêter le Worker crypto (`terminate()`) ; un nouveau Worker reste verrouillé jusqu'à la ressaisie du mot de passe ([[13.04 Protection de la clé privée contre le XSS]]).

Réalisé : module `frontend/src/crypto/idleLock.ts` (clic, frappe, défilement, toucher ; délai mesuré à l'horloge murale ; `freeze` et `pagehide` verrouillent aussitôt), délai publié par `GET /api/v1/instance` (`VAULT_IDLE_MINUTES`, 30 minutes par défaut, au moins 1). En-tête : « Coffre déverrouillé » et bouton Verrouiller ; écran Sécurité : motif du verrouillage et limites. Aide : section *Coffre de clés* de [[43.01 Aperçu de l'interface]] ; [[12.01 Rapport d'analyse du CDC]] §4.3 marqué traité.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
