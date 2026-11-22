# Plan : jusqu'à une première version testable de bout en bout

Plan établi le 2026-11-21 avec `/ecc:plan`, validé par l'utilisateur (« Ok »).

## Objectif

Pouvoir dérouler un parcours de testeur complet, en local comme dans Docker : connexion avec double authentification, création d'un test pour une organisation cliente, périmètre réseau, vulnérabilités cotées en CVSS v3.1 avec remédiation, risque global du test. Hors chemin : GED chiffrée, exports, déroulement des tâches, notifications (dans l'ordre de 22.01 ensuite).

## Étapes

| Étape                | Stories                                                                                                                            | Points     |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| 0. Prérequis         | Détailler 14.01 (fait le 2026-11-21 : phases fixes, table commune + table par type pour le périmètre, ULID, rôle par organisation) | doc        |
| 1. Fin du sprint 3   | [[socle-6]], [[identite-1]], [[identite-3]]                                                                                        | 7          |
| 2. Comptes et droits | [[identite-5]]                                                                                                                     | 5          |
| 3. Le test           | [[engagement-1]], [[engagement-2]], [[engagement-4]]                                                                               | 8          |
| 4. Vulnérabilités    | [[vulnerabilites-1]], [[vulnerabilites-3]], [[vulnerabilites-5]]                                                                   | 9          |
| 5. Synthèse          | [[resultats-1]]                                                                                                                    | 3          |
| 6. Rendre testable   | Commande Artisan du premier administrateur, données de démonstration, procédure de test dans 42.01                                 | hors story |

Total : 32 points, environ deux sprints (fin du sprint 3, sprint 4 réorganisé), 5 à 7 h au rythme mesuré.

## Méthode

Une branche `feature/<id>` par story, tests d'abord, revue par un agent, CI verte, fusion `--no-ff`, story à `done` via le ledger, temps relevé dans 22.01. Chaque story livre sa partie SPA (Vue Router et mise en page commune dès identite-1). Arrêt et consultation de l'utilisateur à chaque décision métier.

## Risques

- [[identite-5]] (ACL, isolation multi-organisation) plus complexe que ses 5 points : isolation testée dès la première ressource, découpage si besoin.
- Partie SPA sous-estimée dans les points : écrans sobres, accessibilité contrôlée par axe.
- Version testable sans chiffrement des preuves : assumé, ged-chiffree-1 à 4 suivent.
- 22.01 à mettre à jour : ce chemin passe les tests et les vulnérabilités avant la GED chiffrée.
