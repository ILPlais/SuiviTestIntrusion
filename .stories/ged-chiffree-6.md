# Story: Révocation forte et douce

**ID:** ged-chiffree-6
**Epic:** ged-chiffree
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que propriétaire, je veux retirer l'accès d'un utilisateur.

## Acceptance Criteria

- [ ] Révocation forte par défaut (nouvelle CEK, rechiffrement, nouveau trousseau)
- [ ] Révocation douce avec avertissement explicite
- [ ] Événements audités

## Technical Notes

Dépend de ged-chiffree-5.

Au départ d'un utilisateur, le défaut est fixé par [[13.05 Trousseau d'équipe et départ d'un utilisateur]] : révocation douce immédiate, révocation forte pour les tests ouverts. Ce cas relève de ged-chiffree-9.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
