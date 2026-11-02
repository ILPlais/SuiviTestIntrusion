# Story: ADR : dérivation de clé (KDF) et protection de la clé privée

**ID:** decisions-2
**Epic:** decisions
**Sprint:** 1
**Status:** done
**Points:** 2

## Context

En tant qu'architecte, je veux spécifier la KDF et ses paramètres afin de protéger correctement la clé privée (12.01 Rapport d'analyse du CDC §1.3).

## Acceptance Criteria

- [x] Algorithme choisi (Argon2id recommandé) et bibliothèque navigateur identifiée
- [x] Paramètres mémoire / itérations / parallélisme et format du sel documentés
- [x] Procédure de rotation du mot de passe décrite (rechiffrement de la clé privée seule)

## Technical Notes

Dépend de decisions-1.

Décision : [[13.02 Dérivation de clé Argon2id]] (Argon2id, S2K OpenPGP t=2 p=4 128 Mio ; pilote argon2id de Laravel pour les comptes locaux). Pas de code pour un ADR : une preuve de faisabilité openpgp.js (paramètres S2K relus dans la clé, rotation sans changer l'empreinte, refus d'Argon2 sans AEAD) et un banc d'essai Firefox / Node tiennent lieu de test, résultats consignés dans l'ADR.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
