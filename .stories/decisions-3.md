# Story: ADR : authentification LDAP/AD et coffre de clés

**ID:** decisions-3
**Epic:** decisions
**Sprint:** 1
**Status:** done
**Points:** 3

## Context

En tant qu'architecte, je veux concilier LDAP/AD avec la clé privée dérivée du mot de passe (12.01 Rapport d'analyse du CDC §1.2).

## Acceptance Criteria

- [x] Choix documenté : passphrase de coffre distincte, clé de récupération, ou autre
- [x] Parcours d'enrôlement et de récupération décrit
- [x] Politique de mot de passe local et verrouillage de compte fixés (§4.1, §4.2)

## Technical Notes

Dépend de decisions-2. Bloquant pour identite.

LDAP/AD reportés en V2 (décision du 2026-11-02). Décision : [[13.03 Authentification locale et coffre de clés]] (mot de passe unique, valeur d'authentification Argon2id dérivée dans le navigateur, clé de récupération, politique NIST SP 800-63B-4, verrouillage par compte). Pas de code pour un ADR : une preuve de faisabilité openpgp.js + argon2id (déterminisme de la valeur d'authentification, récupération, réinitialisation du coffre, détection de réutilisation) et un banc Firefox / Node tiennent lieu de test, résultats consignés dans l'ADR.

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
