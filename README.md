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

| **Partie**       | **Choix**                                                              |
| ---------------- | ---------------------------------------------------------------------- |
| Serveur          | PHP 8.3+, Laravel 11+, API REST versionnée `/api/v1/`, Laravel Sanctum |
| Client           | SPA Vue 3 + Vite + Pinia + vue-i18n                                    |
| Bases de données | SQLite, MySQL / MariaDB ou PostgreSQL, au choix                        |
| Déploiement      | Docker Compose (recommandé) ou installation manuelle                   |

## Contenu du dépôt

Le dépôt est un coffre [Obsidian](https://obsidian.md/).

La documentation de [`docs/`](docs/) est classée selon le système [Johnny.Decimal](https://johnnydecimal.com/) ; l'index complet est la note [`00.00 Index`](docs/00-09%20Syst%C3%A8me/00%20Index/00.00%20Index.md).

| **Domaine**           | **Catégories**                                                                         |
| --------------------- | -------------------------------------------------------------------------------------- |
| `00-09 Système`       | 00 Index, 01 Modèles (Obsidian)                                                        |
| `10-19 Conception`    | 11 Cahier des charges, 12 Analyses et revues, 13 Décisions (ADR), 14 Modèle de données |
| `20-29 Développement` | 21 Organisation et conventions                                                         |
| `30-39 Tests`         | 31 Stratégie, 32 Campagnes                                                             |
| `40-49 Publication`   | 41 Livraison                                                                           |
| `50-59 Retours`       | 51 Traitement, 52 Bugs, 53 Améliorations                                               |

Hors de `docs/` :

| **Chemin**                                       | **Contenu**                                                 |
| ------------------------------------------------ | ----------------------------------------------------------- |
| [`.stories/`](.stories/)                         | Backlog : 10 epics, 74 stories, sprints                     |
| [`CHANGELOG.md`](CHANGELOG.md)                   | Historique des versions                                     |
| [`CLAUDE.md`](CLAUDE.md), [`.claude/`](.claude/) | Contexte et configuration pour Claude Code et le plugin ECC |

## Planification

Le backlog est suivi en fichiers Markdown dans `.stories/`, avec le skill `story-lifecycle` du plugin [ECC](https://github.com/affaan-m/ECC) pour Claude Code.

Le **sprint 1** (du 27 octobre au 9 novembre 2026) a deux objectifs :

1. trancher les points bloquants du cahier des charges par des décisions d'architecture (ADR) : format de chiffrement, dérivation de clé, LDAP/AD et coffre de clés, infrastructure, licence et langues ;
2. poser le socle technique Laravel + Vue 3, avec intégration continue et tests.

## Licence

[MIT](LICENSE) © 2026 PLAIS Lionel
