# Story: ADR : format de chiffrement des preuves

**ID:** decisions-1
**Epic:** decisions
**Sprint:** 1
**Status:** done
**Points:** 3

## Context

En tant qu'architecte, je veux trancher entre OpenPGP (openpgp.js) et un schéma AES-256-GCM + trousseau CEK, afin de supprimer l'ambiguïté signalée dans 12.01 Rapport d'analyse du CDC §1.1.

## Acceptance Criteria

- [x] ADR rédigé avec contexte, options, décision et conséquences
- [x] Format de sérialisation du ciphertext spécifié (version, IV, tag, payload)
- [x] Algorithme de clé asymétrique utilisateur choisi (WebCrypto ou OpenPGP)
- [x] Section « Chiffrement » du CDC alignée sur la décision

## Technical Notes

Bloquant pour l'epic ged-chiffree.

Décision : [[13.01 Format de chiffrement des preuves]] (OpenPGP v6, openpgp.js). Pas de code pour un ADR : une preuve de faisabilité openpgp.js (partage de trousseau sans retoucher le blob, détection d'altération) tient lieu de test, résultats consignés dans l'ADR.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
