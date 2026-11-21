# Story: Authentification locale et sessions Sanctum

**ID:** identite-1
**Epic:** identite
**Sprint:** 3
**Status:** done
**Points:** 3

## Context

En tant qu'utilisateur, je veux me connecter à la SPA de façon sécurisée.

## Acceptance Criteria

- [x] Connexion / déconnexion par session Sanctum
- [x] Politique de mot de passe et verrouillage selon l'ADR
- [x] Rate limiting de la classe Auth appliqué

## Technical Notes

Dépend de decisions-3 et socle-5.

Connexion par valeur d'authentification dérivée dans le navigateur (le mot de passe n'est jamais envoyé), politique de mots de passe et verrouillage de compte : [[13.03 Authentification locale et coffre de clés]].

Le « module crypto unique » de 13.03 est le Worker crypto de [[13.04 Protection de la clé privée contre le XSS]] : le mot de passe lui est transmis pour calculer `auth`, puis n'est conservé nulle part.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
