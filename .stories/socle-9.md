# Story: Interface conforme aux maquettes 15.01

**ID:** socle-9
**Epic:** socle
**Sprint:** 5
**Status:** in-progress
**Points:** 5

## Context

En tant qu'utilisateur, je veux retrouver dans l'application l'interface validée par les maquettes de [[15.01 Maquettes de l'interface]].

## Acceptance Criteria

- [ ] Gabarit : en-tête encadré (titre, activité, navigation, compte) ; dans un test, menu latéral par étape (Synthèse ; Préparer ; Tester) et bandeau du test (fil d'Ariane, référence et titre, statut, type et exposition, risque, dates)
- [ ] Liste des tests (écran 01) : filtres, tableau avec statut et risque en badges, création à la demande
- [ ] Synthèse d'un test (écran 02) : cadres gravité technique, score agrégé et profil d'impact ; compteurs en barres ; fiche et statut du test
- [ ] Périmètre (écran 04) : barre d'actions, tableau des machines avec services, inclusion en badge et contraintes ; formulaires d'ajout à la demande
- [ ] Vulnérabilité (écrans 09 et 10) : badges de sévérité, priorité et statut ; onglets Constat, Cotation et Remédiation ; métriques CVSS en boutons segmentés, score et impact business à côté
- [ ] Accessibilité conservée (axe sans violation dans les deux thèmes), tests de bout en bout adaptés

## Technical Notes

Demandée par l'utilisateur le 2026-11-24, après essai de la version testable : l'interface ne suivait pas les maquettes validées. Seuls les écrans déjà livrés sont concernés ; les sections du menu sans écran (engagement, exécutions, documents, résultats, export, équipe, historique) n'apparaissent qu'avec leurs stories. Styles dans src/styles/base.css (CSP style-src 'self'), d'après docs/10-19 Conception/15 Maquettes/sources/maquettes.css.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
