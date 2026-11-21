# Story: Import d'enregistrements asciinema

**ID:** deroulement-8
**Epic:** deroulement
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant que testeur, je veux joindre l'enregistrement asciinema d'une session interactive, le rejouer et en lire la transcription (12.01 Rapport d'analyse du CDC §2.3).

## Acceptance Criteria

- [ ] Fichiers asciicast v2 et v3 (`.cast`) lus dans le navigateur ; taille maximale paramétrable
- [ ] Exécution créée : commande, début et fin tirés de l'en-tête et des événements ; outil pré-rempli par « Terminal » ; le fichier devient le contenu chiffré, téléchargeable et non modifiable
- [ ] Rejeu par asciinema-player (pause, vitesse, marqueurs) : contenu passé en mémoire, jamais par URL ; `keystrokeOverlay` désactivé ; aucune violation de la CSP de 13.04
- [ ] Transcription texte : événements de sortie, séquences de contrôle retirées ; événements d'entrée exclus
- [ ] Tests sur des enregistrements hostiles : charges XSS, hyperliens OSC 8, OSC 52, événements d'entrée

## Technical Notes

Dépend de deroulement-6. Règles : 11.08, Outils › Import de sorties natives › asciinema (V1).

Lecteur validé sous la CSP par la preuve consignée dans [[13.04 Protection de la clé privée contre le XSS]] (complément du 2026-11-11).

Reportée en V2 (décision du 2026-11-21, [[22.01 Planification du développement]]) : en V1, la sortie d'une session interactive se colle dans une exécution ([[11.08 Déroulement du test]], Outils › Import de sorties natives › asciinema (V2)).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
