# SuiviTestIntrusion

Application Web de suivi de tests d'intrusion. Le projet est en **phase de spécification** : aucun code applicatif n'existe encore. Le dépôt est un coffre Obsidian ; la langue de travail est le **français**.

## Documents

- `Plan/Cahier des charges.md` — spécification de référence.
- `Plan/rapport_cdc.md` — analyse des lacunes du CDC (sections §1 à §6 citées par les stories).
- `Plan/Claude.md`, `Plan/Cursor.md`, `Plan/NotebookLM/` — revues antérieures du CDC.

## Choix techniques retenus

- Back-end : Laravel 11+ / PHP 8.3+, API REST `/api/v1/`, Sanctum.
- Front-end : SPA Vue 3 + Vite + Pinia + vue-i18n (story `socle-1`).
- Chiffrement des preuves 100 % côté navigateur ; le serveur ne voit jamais le clair.

## Planification (ECC)

Le backlog est suivi avec le skill `ecc:story-lifecycle` dans `.stories/` : 10 epics, 61 stories, sprint 1 du 2026-10-27 au 2026-11-09.

- Toute écriture dans `.stories/` passe par `ledger.js` du skill (`node <skill-dir>/scripts/ledger.js <commande> --root <racine>`), jamais à la main.
- L'epic `decisions` (ADR crypto, KDF, LDAP/AD, infra, licence/i18n) passe **avant tout code**. Prochaine étape : `story-lifecycle implement decisions-1`.
- Pas d'issues GitHub : le suivi reste dans les fichiers.

## Reprise sur un autre poste

- `.claude/settings.json` déclare la marketplace ECC et active le plugin `ecc@ecc` ; Claude Code propose de l'installer à l'ouverture du projet.
- `.claude/session-data/` contient une copie des notes de session ECC. Pour `/ecc:resume-session`, les copier d'abord dans `~/.claude/session-data/`.

## Conventions

- Commits en français, directement sur `main`.
- Formules CVSS en LaTeX dans le CDC ; les tableaux contiennent des espaces insécables après les emojis.
