# SuiviTestIntrusion

Application Web de **suivi de tests d'intrusion** : elle accompagne une mission de pentest de bout en bout, du questionnaire d'engagement avec le client jusqu'au rapport final, en conservant les preuves de façon chiffrée.

[![GitHub License](https://img.shields.io/github/license/ILPlais/SuiviTestIntrusion?style=for-the-badge)](LICENSE)
[![GitHub Repo stars](https://img.shields.io/github/stars/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://gitHub.com/ILPlais/SuiviTestIntrusion/stargazers/)
[![GitHub forks](https://img.shields.io/github/forks/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://gitHub.com/ILPlais/SuiviTestIntrusion/network/)
[![GitHub watchers](https://img.shields.io/github/watchers/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://github.com/ILPlais/SuiviTestIntrusion/watchers)

> **État du projet :** début du développement. Le cahier des charges et les décisions d'architecture sont rédigés ; le socle Laravel + Vue 3 est en place.

![GitHub Created At](https://img.shields.io/github/created-at/ILPlais/SuiviTestIntrusion?style=for-the-badge)
![GitHub Release Date](https://img.shields.io/github/release-date/ILPlais/SuiviTeestIntrusion?style=for-the-badge)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/ILPlais/SuiviTestIntrusion/total?style=for-the-badge)

[![GitHub branch count](https://img.shields.io/github/branches/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://github.com/ILPlais/SuiviTestIntrusion/branches)
[![GitHub last commit](https://img.shields.io/github/last-commit/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://github.com/ILPlais/SuiviTestIntrusion/commits/main/)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/ILPlais/SuiviTestIntrusion/ci.yml?style=for-the-badge)


[![GitHub contributors](https://img.shields.io/github/contributors/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://github.com/ILPlais/SuiviTestIntrusion/graphs/contributors/)
![GitHub language count](https://img.shields.io/github/languages/count/ILPlais/SuiviTestIntrusion?style=for-the-badge)
![GitHub top language](https://img.shields.io/github/languages/top/ILPlais/SuiviTestIntrusion?style=for-the-badge)
[![GitHub Issues](https://img.shields.io/github/issues-raw/ILPlais/SuiviTestIntrusion?style=for-the-badge)](https://github.com/ILPlais/SuiviTestIntrusion/issues)

![GitHub repo file or directory count](https://img.shields.io/github/directory-file-count/ILPlais/SuiviTestIntrusion?style=for-the-badge)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/ILPlais/SuiviTestIntrusion?style=for-the-badge)

## Fonctionnalités prévues

- **Engagement** : références (prestataire, testeurs, clients), questionnaire d'engagement, périmètre (réseau, applicatif, physique, radio, ingénierie sociale).
- **Déroulement** : phases (reconnaissance → nettoyage), tâches attribuées aux testeurs, planning avec jalons et vue Gantt, outils et commandes exécutées avec coloration syntaxique, diagramme réseau Mermaid.
- **Vulnérabilités** : fiches CVE/CWE, score CVSS v3.1 (v4.0 en option), impact business (opérationnel, légal, financier, image), fiches de remédiation avec priorités P1–P4.
- **GED chiffrée de bout en bout** : les preuves sont chiffrées et déchiffrées **dans le navigateur** ; le serveur ne stocke que des fichiers chiffrés et ne voit jamais leur contenu.
- **Résultats** : tableau de bord du risque global, graphiques, conclusions, mesures et contre-mesures.
- **Exports** : rapports PDF, OpenDocument et HTML (modèles exécutif, technique, annexes de preuves), export JSON/XML pour la migration entre instances.
- **Utilisateurs** : comptes locaux, double authentification TOTP, rôles et ACL, isolation stricte des données par client (multi-tenant) ; LDAP / Active Directory en V2.
- **Exploitation** : notifications dans l'application, journal d'audit, sauvegardes avec [Portabase](https://portabase.io/), interface en français prête pour d'autres langues.

## Technologies prévues

| **Partie**       | **Choix**                                                              |
| ---------------- | ---------------------------------------------------------------------- |
| Serveur          | PHP 8.3+, Laravel 11+, API REST versionnée `/api/v1/`, Laravel Sanctum |
| Client           | SPA Vue 3 + Vite + Pinia + vue-i18n                                    |
| Bases de données | SQLite, MySQL / MariaDB ou PostgreSQL, au choix                        |
| Déploiement      | Docker Compose (recommandé) derrière un proxy TLS, ou installation manuelle |

## Démarrage (développement)

Prérequis : PHP 8.3+ avec Composer, Node.js 20+ (sous Windows, un sous-système Linux WSL convient).

```bash
cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan serve
cd frontend && npm install && npm run dev
```

L'interface est alors disponible sur http://localhost:5173 ; détails dans [`backend/README.md`](backend/README.md) et [`frontend/README.md`](frontend/README.md).

## Installation (production)

- Docker Compose (recommandé) : [`42.01 Installation avec Docker Compose`](docs/40-49%20Publication/42%20Installation/42.01%20Installation%20avec%20Docker%20Compose.md) ;
- installation manuelle (PHP-FPM, nginx) : [`42.02 Installation manuelle`](docs/40-49%20Publication/42%20Installation/42.02%20Installation%20manuelle.md).

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

Projet personnel mené sur du temps libre : les sprints (lots d'environ 15 points) n'ont pas de dates, et seul le temps réellement passé est relevé. La planification en points et le Gantt sont dans [`22.01 Planification du développement`](docs/20-29%20D%C3%A9veloppement/22%20Planification/22.01%20Planification%20du%20d%C3%A9veloppement.md) ; la feuille de route V1 / V2 dans [`11.01 Cahier des charges`](docs/10-19%20Conception/11%20Cahier%20des%20charges/11.01%20Cahier%20des%20charges.md).

Les décisions d'architecture (ADR 13.01 à 13.07) sont tranchées ; la suite du sprint 2 pose le socle technique Laravel + Vue 3, avec intégration continue et tests.

## Licence

[MIT](LICENSE) © 2026 PLAIS Lionel
