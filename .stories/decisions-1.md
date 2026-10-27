# Story: ADR : format de chiffrement des preuves

**ID:** decisions-1
**Epic:** decisions
**Sprint:** 1
**Status:** todo
**Points:** 3

## Context

En tant qu'architecte, je veux trancher entre OpenPGP (openpgp.js) et un schéma AES-256-GCM + trousseau CEK, afin de supprimer l'ambiguïté signalée dans 12.01 Rapport d'analyse du CDC §1.1.

## Acceptance Criteria

- [ ] ADR rédigé avec contexte, options, décision et conséquences
- [ ] Format de sérialisation du ciphertext spécifié (version, IV, tag, payload)
- [ ] Algorithme de clé asymétrique utilisateur choisi (WebCrypto ou OpenPGP)
- [ ] Section « Chiffrement » du CDC alignée sur la décision

## Technical Notes

Bloquant pour l'epic ged-chiffree.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
