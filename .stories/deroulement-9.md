# Story: Import Nessus, Burp Suite et OWASP ZAP

**ID:** deroulement-9
**Epic:** deroulement
**Sprint:** unassigned
**Status:** todo
**Points:** 8

## Context

En tant que testeur, je veux importer les résultats d'un scanner de vulnérabilités plutôt que ressaisir chaque constat (12.01 Rapport d'analyse du CDC §2.3).

## Acceptance Criteria

- [ ] Formats `.nessus`, export XML de Burp Suite, rapport XML ou JSON d'OWASP ZAP, analysés dans le navigateur
- [ ] Vulnérabilités brouillon : titre, CVE/CWE, description, composants concernés ; validation par un testeur
- [ ] Dédoublonnage avec les vulnérabilités existantes du test
- [ ] Composants rapprochés des objets du périmètre ; cible hors périmètre signalée
- [ ] Fichier conservé comme contenu chiffré de l'exécution
- [ ] Tests sur des fichiers réels et hostiles

## Technical Notes

Dépend de deroulement-7 et vulnerabilites-3.

Reportée en V2 : en V1, seuls Nmap XML et asciinema s'importent ; la sortie d'un scanner se colle dans une exécution et ses constats se saisissent à la main ([[11.08 Déroulement du test]], Outils › Import de sorties natives).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
