# SuiviTestIntrusion

Application Web de **suivi de tests d'intrusion** : elle accompagne une mission de pentest de bout en bout, du questionnaire d'engagement avec le client jusqu'au rapport final, en conservant les preuves de façon chiffrée.

> **État du projet :** phase de spécification. Le cahier des charges est rédigé et le backlog est planifié ; aucun code applicatif n'existe encore.

## Fonctionnalités prévues

- **Engagement** : références (prestataire, testeurs, clients), questionnaire d'engagement, périmètre (réseau, applicatif, physique, radio, ingénierie sociale).
- **Déroulement** : phases (reconnaissance → nettoyage), tâches attribuées aux testeurs, planning avec jalons et vue Gantt, outils et commandes exécutées avec coloration syntaxique, diagramme réseau Mermaid.
- **Vulnérabilités** : fiches CVE/CWE, score CVSS v3.1 (v4.0 en option), impact business (opérationnel, légal, financier, image), fiches de remédiation avec priorités P1–P4.
- **GED chiffrée de bout en bout** : les preuves sont chiffrées et déchiffrées **dans le navigateur** ; le serveur ne stocke que des fichiers chiffrés et ne voit jamais leur contenu.
- **Résultats** : tableau de bord du risque global, graphiques, conclusions, mesures et contre-mesures.
- **Exports** : rapports PDF, OpenDocument et HTML (modèles exécutif, technique, annexes de preuves), export JSON/XML pour la migration entre instances.
- **Utilisateurs** : comptes locaux, LDAP ou Active Directory, double authentification (e-mail ou TOTP), rôles et ACL, isolation stricte des données par client (multi-tenant).
- **Exploitation** : notifications, journal d'audit, sauvegardes avec [Portabase](https://portabase.io/), interface multilingue.

## Technologies prévues

| Partie | Choix |
| --- | --- |
| Serveur | PHP 8.3+, Laravel 11+, API REST versionnée `/api/v1/`, Laravel Sanctum |
| Client | SPA Vue 3 + Vite + Pinia + vue-i18n |
| Bases de données | SQLite, MySQL / MariaDB ou PostgreSQL, au choix |
| Déploiement | Docker Compose (recommandé) ou installation manuelle |

## Contenu du dépôt

Le dépôt est un coffre [Obsidian](https://obsidian.md/).

| Chemin | Contenu |
| --- | --- |
| [`Plan/Cahier des charges.md`](Plan/Cahier%20des%20charges.md) | Spécification de référence |
| [`Plan/rapport_cdc.md`](Plan/rapport_cdc.md) | Analyse des lacunes du cahier des charges |
| `Plan/Claude.md`, `Plan/Cursor.md`, `Plan/NotebookLM/` | Revues antérieures du cahier des charges |
| `.stories/` | Backlog : 10 epics, 61 stories, sprints |
| `CLAUDE.md`, `.claude/` | Contexte et configuration pour Claude Code et le plugin ECC |

## Planification

Le backlog est suivi en fichiers Markdown dans `.stories/`, avec le skill `story-lifecycle` du plugin [ECC](https://github.com/affaan-m/ECC) pour Claude Code.

Le **sprint 1** (du 27 octobre au 9 novembre 2026) a deux objectifs :

1. trancher les points bloquants du cahier des charges par des décisions d'architecture (ADR) : format de chiffrement, dérivation de clé, LDAP/AD et coffre de clés, infrastructure, licence et langues ;
2. poser le socle technique Laravel + Vue 3, avec intégration continue et tests.

## Licence

[MIT](LICENSE) © 2026 PLAIS Lionel
