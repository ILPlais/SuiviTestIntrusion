# Story: Paire de clés utilisateur et coffre

**ID:** ged-chiffree-1
**Epic:** ged-chiffree
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant qu'utilisateur, je veux une paire de clés protégée par mon secret.

## Acceptance Criteria

- [ ] Génération côté navigateur selon l'ADR crypto
- [ ] Clé privée chiffrée via la KDF choisie et stockée sur le serveur
- [ ] Rotation du mot de passe sans rechiffrer les fichiers
- [ ] Clé privée déchiffrée, CEK et mot de passe confinés dans le Worker crypto (13.04)

## Technical Notes

Dépend de decisions-1, decisions-2, decisions-7, identite-1. Worker crypto : [[13.04 Protection de la clé privée contre le XSS]].

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
