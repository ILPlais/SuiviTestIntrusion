# Story: Authentification locale et sessions Sanctum

**ID:** identite-1
**Epic:** identite
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant qu'utilisateur, je veux me connecter à la SPA de façon sécurisée.

## Acceptance Criteria

- [ ] Connexion / déconnexion par session Sanctum
- [ ] Politique de mot de passe et verrouillage selon l'ADR
- [ ] Rate limiting de la classe Auth appliqué

## Technical Notes

Dépend de decisions-3 et socle-5.

Connexion par valeur d'authentification dérivée dans le navigateur (le mot de passe n'est jamais envoyé), politique de mots de passe et verrouillage de compte : [[13.03 Authentification locale et coffre de clés]].

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
