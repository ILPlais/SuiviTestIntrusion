# Story: Import Nmap XML

**ID:** deroulement-7
**Epic:** deroulement
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que testeur, je veux importer un fichier Nmap XML plutôt que recopier sa sortie (12.01 Rapport d'analyse du CDC §2.3).

## Acceptance Criteria

- [ ] Fichier `nmap -oX` lu et analysé dans le navigateur, sans résolution d'entités externes ; taille maximale paramétrable
- [ ] Exécution créée : version, commande, début et fin tirés du fichier ; le fichier devient le contenu chiffré, téléchargeable et non modifiable
- [ ] Hôtes actifs rapprochés des machines du périmètre par adresse IP ; ports ouverts proposés comme services ; validation par le testeur
- [ ] Machines rapprochées liées comme cibles de l'exécution
- [ ] Hôte correspondant à une machine exclue signalé au chef de projet ; hôte inconnu listé, son ajout au périmètre réservé au chef de projet
- [ ] Tests sur des fichiers Nmap réels et hostiles : charges XSS, entités, fichier tronqué ou mal formé

## Technical Notes

Dépend de deroulement-6 et engagement-4. Règles : 11.08, Outils › Import de sorties natives. Les valeurs extraites s'affichent comme du texte (13.04).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
