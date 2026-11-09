# SuiviTestIntrusion

Application Web de suivi de tests d'intrusion. Le projet est en **phase de spécification** : aucun code applicatif n'existe encore. Le dépôt est un coffre Obsidian ; la langue de travail est le **français**.

## Documents

`docs/` est classé en Johnny.Decimal (domaine `10-19` / catégorie `11` / identifiant `11.01`, en tête du nom de fichier). Index complet : `docs/00-09 Système/00 Index/00.00 Index.md`. Les documents se citent par leur identifiant ; `xx.00` = index de la catégorie ; un identifiant n'est jamais réutilisé.

- `11.01 Cahier des charges` — spécification de référence : objectifs du projet et sommaire ; `11.02`–`11.13` — ses parties (socle, API, exports, engagement, utilisateurs, périmètre, déroulement, vulnérabilités, cotation CVSS, documents et chiffrement, résultats, annexes).
- `12.01 Rapport d'analyse du CDC` — lacunes du CDC (sections §1 à §6 citées par les stories) ; `12.02`–`12.05` — revues antérieures (Claude, Cursor, NotebookLM).
- `13.xx` — ADR (index `13.00`, modèle `01.01`).
- `14.01 Schéma des objets métier` — liaisons entre objets métier (schéma simplifié), base des modèles Eloquent et des migrations ; liens à détailler avant la première migration métier.
- `21.01` organisation du code ; `31.01` stratégie de tests, `32.xx` campagnes ; `41.01` versionnage et livraison (`CHANGELOG.md` à la racine).
- `51.01` traitement des retours ; `52.xx` bugs, `53.xx` améliorations ; une fiche acceptée devient une story dans `.stories/`.
- `01 Modèles` — modèles Obsidian (dossier configuré dans `.obsidian/templates.json`).
- Hors classement : `.stories/`, `CHANGELOG.md`, et le code prévu dans `backend/` (Laravel) et `frontend/` (Vue), créés par `socle-1`.

## Choix techniques retenus

- Back-end : Laravel 11+ / PHP 8.3+, API REST `/api/v1/`, Sanctum.
- Front-end : SPA Vue 3 + Vite + Pinia + vue-i18n (story `socle-1`).
- Chiffrement des preuves 100 % côté navigateur ; le serveur ne voit jamais le clair.

## Planification (ECC)

Le backlog est suivi avec le skill `ecc:story-lifecycle` dans `.stories/` : 10 epics, 63 stories, sprint 1 du 2026-10-27 au 2026-11-09.

- Toute écriture dans `.stories/` passe par `ledger.js` du skill (`node <skill-dir>/scripts/ledger.js <commande> --root <racine>`), jamais à la main.
- L'epic `decisions` (ADR crypto, KDF, LDAP/AD, infra, licence/i18n) passe **avant tout code**. Prochaine étape : `story-lifecycle implement decisions-1`.
- Pas d'issues GitHub : le suivi reste dans les fichiers.

## Reprise sur un autre poste

- `.claude/settings.json` déclare la marketplace ECC et active le plugin `ecc@ecc` ; Claude Code propose de l'installer à l'ouverture du projet.
- `.claude/session-data/` contient une copie des notes de session ECC. Pour `/ecc:resume-session`, les copier d'abord dans `~/.claude/session-data/`.
- Chaque session avec Claude doit être exportée dans le dossier `.claude/session-data/` et commitée.

## Conventions

- Commits en français, directement sur `main`.
- Formules CVSS en LaTeX dans le CDC (`11.10`) ; les tableaux contiennent des espaces insécables après les emojis.
