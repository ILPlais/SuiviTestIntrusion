# Story: Téléchargement et déchiffrement

**ID:** ged-chiffree-4
**Epic:** ged-chiffree
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant qu'utilisateur autorisé, je veux consulter une preuve.

## Acceptance Criteria

- [ ] Déchiffrement local de la CEK puis du fichier
- [ ] Vérification du hash du ciphertext
- [ ] Aperçu en ligne limité aux images PNG, JPEG, WebP et GIF et au texte ; autres types téléchargés seulement (13.04)

## Technical Notes

Dépend de ged-chiffree-3.

Règles d'aperçu et de téléchargement : [[13.04 Protection de la clé privée contre le XSS]].

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
