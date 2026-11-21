# SuiviTestIntrusion

Application Web de suivi de tests d'intrusion. Le projet est en **début de développement** : le socle Laravel 13 (`backend/`) et Vue 3 (`frontend/`) est en place (socle-1). Le dépôt est un coffre Obsidian ; la langue de travail est le **français**.

## Documents

`docs/` est classé en Johnny.Decimal (domaine `10-19` / catégorie `11` / identifiant `11.01`, en tête du nom de fichier). Index complet : `docs/00-09 Système/00 Index/00.00 Index.md`. Les documents se citent par leur identifiant ; `xx.00` = index de la catégorie ; un identifiant n'est jamais réutilisé.

- `11.01 Cahier des charges` — spécification de référence : objectifs du projet et sommaire ; `11.02`–`11.13` — ses parties (socle, API, exports, engagement, utilisateurs, périmètre, déroulement, vulnérabilités, cotation CVSS, documents et chiffrement, résultats, annexes).
- `12.01 Rapport d'analyse du CDC` — lacunes du CDC (sections §1 à §6 citées par les stories) ; `12.02`–`12.05` — revues antérieures (Claude, Cursor, NotebookLM).
- `13.xx` — ADR (index `13.00`, modèle `01.01`).
- `14.01 Schéma des objets métier` — liaisons entre objets métier (schéma simplifié), base des modèles Eloquent et des migrations ; liens à détailler avant la première migration métier.
- `21.01` organisation du code ; `22.01` planification (Gantt en points, temps passé, à régénérer en fin de sprint) ; `31.01` stratégie de tests, `32.xx` campagnes ; `41.01` versionnage et livraison (`CHANGELOG.md` à la racine).
- `51.01` traitement des retours ; `52.xx` bugs, `53.xx` améliorations ; une fiche acceptée devient une story dans `.stories/`.
- `01 Modèles` — modèles Obsidian (dossier configuré dans `.obsidian/templates.json`).
- Hors classement : `.stories/`, `CHANGELOG.md`, et le code prévu dans `backend/` (Laravel) et `frontend/` (Vue), créés par `socle-1`.

## Choix techniques retenus

- Back-end : Laravel 11+ / PHP 8.3+, API REST `/api/v1/`, Sanctum.
- Front-end : SPA Vue 3 + Vite + Pinia + vue-i18n (story `socle-1`).
- Chiffrement des preuves 100 % côté navigateur ; le serveur ne voit jamais le clair.
- Outils de développement dans WSL (Ubuntu-26.04 : PHP 8.5, Composer, Node 22) ; depuis Git Bash : `MSYS_NO_PATHCONV=1 wsl.exe -d Ubuntu-26.04 --cd /mnt/c/.../SuiviTestIntrusion/backend --exec bash --login -c "php artisan test"`. GitHub CLI : `/c/Program Files/GitHub CLI/gh.exe` (authentifié) ; lire l'état de la CI (`gh run list --branch feature/<id>`) avant chaque fusion.

## Planification (ECC)

Le backlog est suivi avec le skill `ecc:story-lifecycle` dans `.stories/` : 10 epics, 74 stories (55 en V1 dont 11 terminées, 19 reportées en V2 : mention « Reportée en V2 » dans la story), sprint 2 terminé ; planification en points dans `22.01`.

- Projet personnel mené sur du temps libre : aucune date prévue (ni sprint, ni livraison). Un sprint est un lot d'environ 15 points ; seul le **temps réellement passé** compte, noté dans le tableau de `22.01` à la fin de chaque story.

- Toute écriture dans `.stories/` passe par `ledger.js` du skill (`node <skill-dir>/scripts/ledger.js <commande> --root <racine>`), jamais à la main.
- L'epic `decisions` (ADR crypto, KDF, LDAP/AD, infra, licence/i18n) passe **avant tout code**. Prochaine étape : `story-lifecycle plan-sprint 3` (environ 15 points, ordre de `22.01`).
- Pas d'issues GitHub : le suivi reste dans les fichiers.

## Reprise sur un autre poste

- `.claude/settings.json` déclare la marketplace ECC et active le plugin `ecc@ecc` ; Claude Code propose de l'installer à l'ouverture du projet.
- `.claude/session-data/` contient une copie des notes de session ECC. Pour `/ecc:resume-session`, les copier d'abord dans `~/.claude/session-data/`.
- Chaque session avec Claude doit être exportée dans le dossier `.claude/session-data/` et commitée.
- Chaque plan (mode plan, `/ecc:plan`) concernant ce projet doit être copié de `~/.claude/plans/` vers `.claude/plans/` (nom de fichier inchangé), commité puis poussé sur GitHub (`git push origin main`) en fin de session.

## Conventions

- Commits en français.
- Style du code (`21.01`) : indentation par tabulations (`.editorconfig`), code bien commenté en français (en-tête de chaque fichier, classe et fonction exportée, pourquoi des choix non évidents), noms longs des options de commandes (`--message`, `--set-upstream`, `--no-interaction`…), y compris dans les commandes que Claude lance.
- Chaque fonctionnalité (story) est développée dans sa propre branche `feature/<id>` (correctifs : `fix/<id>`), créée depuis `main`, publiée sur GitHub (`git push --set-upstream origin feature/<id>`), puis intégrée à `main` par `git merge --no-ff` une fois revue et testée ; la branche est ensuite supprimée. Détail : `21.01`.
- Restent directement sur `main` : documentation, `.stories/`, notes de session et plans (`.claude/`), commits de version.
- Formules CVSS en LaTeX dans le CDC (`11.10`) ; les tableaux contiennent des espaces insécables après les emojis.
