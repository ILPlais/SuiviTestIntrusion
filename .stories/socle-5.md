# Story: Rate limiting par classe et limite de taille

**ID:** socle-5
**Epic:** socle
**Sprint:** 3
**Status:** done
**Points:** 2

## Context

En tant qu'exploitant, je veux protéger le serveur contre la saturation et le bourrage d'identifiants.

## Acceptance Criteria

- [x] Classes Standard, Écriture lourde, Export et Auth avec les plafonds par défaut du CDC, réglables
- [x] Réponse 429 `rate_limited` avec `X-RateLimit-*` et `Retry-After`, événement audité
- [x] Corps JSON limité à 2 Mo par défaut (413 `payload_too_large`)

## Technical Notes

Dépend de socle-4. L'audit est branché par identite-6.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
