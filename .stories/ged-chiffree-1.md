# Story: Paire de clés utilisateur et coffre

**ID:** ged-chiffree-1
**Epic:** ged-chiffree
**Sprint:** 6
**Status:** done
**Points:** 8

## Context

En tant qu'utilisateur, je veux une paire de clés protégée par mon secret.

## Acceptance Criteria

- [x] Génération côté navigateur selon l'ADR crypto
- [x] Clé privée chiffrée via la KDF choisie et stockée sur le serveur
- [x] Rotation du mot de passe sans rechiffrer les fichiers
- [x] Clé privée déchiffrée, CEK et mot de passe confinés dans le Worker crypto (13.04)
- [x] Clé de récupération de 128 bits générée à l'enrôlement, affichée une seule fois en groupes de quatre caractères, jamais envoyée au serveur ; elle chiffre une seconde copie de la clé privée (même empreinte que la copie mot de passe)
- [x] Ressaisie de la clé de récupération obligatoire avant l'activation du compte (écran 17 de [[15.01 Maquettes de l'interface]])
- [x] Déverrouillage de la seconde copie par la clé de récupération (saisie en minuscules, avec tirets), nouveau mot de passe et nouvelle clé de récupération ; anciens mot de passe et clé refusés (le lien de réinitialisation relève d'identite-7)

## Technical Notes

Dépend de decisions-1, decisions-2, decisions-7, identite-1. Worker crypto : [[13.04 Protection de la clé privée contre le XSS]]. Clé de récupération : [[13.03 Authentification locale et coffre de clés]] et [[11.06 Gestion des utilisateurs#Clé de récupération|11.06, Clé de récupération]] ; ajoutée au sprint 6 (aucune story ne la portait), points passés de 5 à 8.

Réalisé : openpgp.js 6.3.3 (LGPL-3.0+, exception dans `scripts/licenses/policy.json`) chargé dans son propre fichier par le Worker crypto ; paire v6 curve25519, S2K Argon2id (t=2, p=4, 128 Mio, plafond accepté 2 Gio / t=8), AEAD GCM. Colonnes `users.public_key`, `key_fingerprint`, `private_key_password_copy`, `private_key_recovery_copy`, `key_version` (verrou optimiste) ; API `GET /api/v1/user/vault` et `PUT /api/v1/user/password` (409 si la copie a changé entre-temps, audit `auth.password_changed`). Clé de récupération en Base32 de Crockford (26 caractères, saisie tolérante : casse, espaces, tirets, O/I/L). L'opération `recover` du Worker est prête et testée ; sa route serveur (lien de réinitialisation) est livrée par identite-7.

Correction annexe : la SPA compilée est servie en `Referrer-Policy: no-referrer` ; ses GET de même origine n'envoyaient ni Origin ni Referer, et Sanctum ignorait la session (401). Les requêtes de l'API fixent `referrerPolicy: 'same-origin'`.

Reste hors story : historique des 5 derniers mots de passe (13.03) ; renouvellement automatique d'une copie chiffrée avec des paramètres S2K périmés (signalé par le Worker, pas encore renvoyé au serveur) ; second facteur pendant l'enrôlement (il se configure ensuite dans l'écran Sécurité).

## Definition of Done

- [x] Tests written first cover the acceptance criteria
- [x] Code review and verification loop passed
- [x] Code merged to main
