# Story: Paire de clés utilisateur et coffre

**ID:** ged-chiffree-1
**Epic:** ged-chiffree
**Sprint:** 6
**Status:** in-progress
**Points:** 8

## Context

En tant qu'utilisateur, je veux une paire de clés protégée par mon secret.

## Acceptance Criteria

- [ ] Génération côté navigateur selon l'ADR crypto
- [ ] Clé privée chiffrée via la KDF choisie et stockée sur le serveur
- [ ] Rotation du mot de passe sans rechiffrer les fichiers
- [ ] Clé privée déchiffrée, CEK et mot de passe confinés dans le Worker crypto (13.04)
- [ ] Clé de récupération de 128 bits générée à l'enrôlement, affichée une seule fois en groupes de quatre caractères, jamais envoyée au serveur ; elle chiffre une seconde copie de la clé privée (même empreinte que la copie mot de passe)
- [ ] Ressaisie de la clé de récupération obligatoire avant l'activation du compte (écran 17 de [[15.01 Maquettes de l'interface]])
- [ ] Déverrouillage de la seconde copie par la clé de récupération (saisie en minuscules, avec tirets), nouveau mot de passe et nouvelle clé de récupération ; anciens mot de passe et clé refusés (le lien de réinitialisation relève d'identite-7)

## Technical Notes

Dépend de decisions-1, decisions-2, decisions-7, identite-1. Worker crypto : [[13.04 Protection de la clé privée contre le XSS]]. Clé de récupération : [[13.03 Authentification locale et coffre de clés]] et [[11.06 Gestion des utilisateurs#Clé de récupération|11.06, Clé de récupération]] ; ajoutée au sprint 6 (aucune story ne la portait), points passés de 5 à 8.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
