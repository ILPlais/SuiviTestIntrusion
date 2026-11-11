# Plan : decisions-1 — ADR format de chiffrement des preuves

**Story** : `.stories/decisions-1.md` (3 pts, sprint 1) · **Complexité** : faible (documentation seule)

## Contexte
Le CDC 11.01 §Chiffrement annonce « GPG/OpenPGP » (l. 712) mais décrit AES-256-GCM + trousseau CEK (l. 773-793). 12.01 §1.1 classe ce point 🔴 bloquant ; il bloque l'epic `ged-chiffree`. Décision utilisateur (2026-11-02) : **OpenPGP via openpgp.js**, sans exigence de lecture par `gpg` hors application.

## Décision à consigner (contenu de l'ADR)
- **Norme** : OpenPGP RFC 9580 (v6), openpgp.js v6.
- **Fichier (blob)** : un seul paquet SEIPDv2, AEAD **AES-256-GCM** (cohérent avec le CDC, implémenté via WebCrypto par openpgp.js), découpage AEAD en blocs (`aeadChunkSizeByte`, valeur à fixer, ex. 2^18 o) pour le streaming des gros fichiers. Pas de PKESK dans le blob.
- **CEK** = *session key* OpenPGP (AES-256, aléatoire par fichier, et nouvelle à chaque révocation forte).
- **Trousseau** : une entrée = un paquet PKESK v6 détaché, stocké en base par (fichier, utilisateur). Partage : `decryptSessionKeys` puis `encryptSessionKey` pour la clé du bénéficiaire, sans toucher au blob (conforme à 11.01 l. 780-787). Déchiffrement : `decrypt({ sessionKeys })`.
- **Clés utilisateur** : clé OpenPGP v6, Ed25519 (signature) + X25519 (chiffrement). Clé privée protégée par S2K OpenPGP ; l'algorithme et ses paramètres (Argon2id attendu) relèvent de **decisions-2**, l'ADR y renvoie.
- **Version du schéma** : les paquets OpenPGP portent déjà leur version, IV et tag ; on ajoute en base une colonne `schema_crypto` (ex. `openpgp-v6/seipd2-aes256-gcm`) pour permettre une migration future.
- **Constat de la preuve (à écrire dans l'ADR)** : un PKESK v6 ne transporte **pas** l'algorithme symétrique (`decryptSessionKeys` renvoie `algorithm: null`, sans `aeadAlgorithm`). Le client doit donc réinjecter `aes256`/`gcm` lus dans `schema_crypto` avant `encryptSessionKey` (partage) et `decrypt`. C'est une raison de plus pour garder cette colonne.
- **Mesures de la preuve** : une entrée de trousseau (PKESK v6 X25519) fait 111 octets ; le blob ne contient qu'un paquet SEIPD ; un octet altéré est rejeté (« unable to authenticate data »).
- **Options écartées** : schéma maison WebCrypto (format à spécifier et maintenir soi-même, pas de streaming natif) ; OpenPGP compatible GnuPG (OCB LibrePGP ou CFB+MDC), inutile faute de besoin hors application.
- **Conséquences** : dépendance openpgp.js (~150 ko, à épingler) côté SPA ; vérification d'empreinte des clés hors bande (11.01 l. 808) facilitée par les empreintes v6 ; GCM en SEIPDv2 non lisible par GnuPG 2.4 (assumé) ; CSP stricte toujours requise (12.01).

## Fichiers à modifier
| Fichier | Action | Pourquoi |
|---|---|---|
| `docs/10-19 Conception/13 Décisions d'architecture/13.01 Format de chiffrement des preuves.md` | CRÉER | ADR depuis le modèle `01.01 Modèle ADR` (frontmatter `statut`, `date`, `story: decisions-1`) |
| `docs/10-19 Conception/13 Décisions d'architecture/13.00 Index des ADR.md` | MAJ | Ligne 13.01 dans le tableau |
| `docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md` | MAJ | §Chiffrement l. 710-793 : OpenPGP v6 partout, CEK = session key, trousseau = PKESK, renvoi `[[13.01 …]]` ; glossaire l. 921 (CEK) |
| `docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md` | MAJ | §1.1 et tableau l. 295 : mention « traité par 13.01 » |
| `.stories/decisions-1.md` | via `ledger.js` | Statut in-progress puis done, cases cochées |

## Étapes
1. ✅ `ledger.js` : decisions-1 en `in-progress` (seuls `decisions-1.md`, `epics/decisions.md` et `sprints/sprint-1.md` ont changé).
2. ✅ **Preuve** (openpgp 6.1.1 dans le scratchpad, non commitée) : clés v6 Ed25519/X25519, blob SEIPD seul, partage A → B sans modifier le blob (hash identique), clair identique, altération détectée. Reste à corriger `scratchpad/poc/poc.mjs` (réinjection de l'algorithme) pour qu'il passe tel quel.
3. Rédiger l'ADR 13.01 (statut `accepté` si tu valides, sinon `proposé`).
4. Mettre à jour 13.00, 11.01 et 12.01.
5. `ledger.js` : cocher les critères et passer en done. Commit en français sur `main`.

## Vérification
- Le script de l'étape 2 affiche le clair identique pour A et B, et le blob est inchangé (même hash).
- `grep -n "GPG" 11.01` : il ne reste que la ligne 192 (sauvegardes, hors sujet).
- Les liens Obsidian `[[13.01 …]]` sont résolus et les 4 critères d'acceptation sont couverts.

## Risques
| Risque | Probabilité | Mitigation |
|---|---|---|
| L'API openpgp.js ne permet pas un SEIPD sans PKESK ou un PKESK détaché | Levé | Vérifié par la preuve ; seule contrainte : réinjecter l'algorithme (voir constat) |
| Chevauchement avec decisions-2 (S2K/Argon2) | Moyenne | L'ADR 13.01 renvoie explicitement les paramètres à decisions-2 |
| La « Definition of Done » de la story exige des tests | — | Pour un ADR, l'étape 2 tient lieu de test ; je le signalerai dans la story |
