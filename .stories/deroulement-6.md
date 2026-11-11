# Story: Catalogue d'outils et exécutions

**ID:** deroulement-6
**Epic:** deroulement
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que testeur, je veux consigner les commandes et leurs sorties, sans que le serveur voie les secrets qu'elles contiennent (12.01 Rapport d'analyse du CDC §2.3).

## Acceptance Criteria

- [x] Structure d'une exécution d'outil documentée dans le CDC (11.08, Outils)
- [ ] Catalogue d'outils commun au tenant, pré-rempli, complété par les testeurs : nom, lien, langages PrismJS par défaut
- [ ] Exécution : référence, outil et version, auteur, dates de début et de fin, phase, commande facultative et sortie avec leur langage, origine
- [ ] Commande et sortie chiffrées dans le navigateur en un contenu unique (13.01), stocké hors base ; trousseau initial : auteur, chef de projet et testeurs affectés au test ; client final exclu
- [ ] Liens n-n vers les tâches, les vulnérabilités et les objets du périmètre visés
- [ ] Métadonnées seules en clair : la recherche et l'historique n'exposent jamais le contenu
- [ ] Coloration PrismJS sur les langages minimaux du CDC ; sortie tronquée au-delà de 1 Mio (paramétrable), texte complet téléchargeable

## Technical Notes

Dépend de deroulement-1 et ged-chiffree-3. Modèle : 11.08, Outils. Les imports relèvent de deroulement-7 (Nmap XML), deroulement-8 (asciinema) et deroulement-9 (scanners, V2).

Rendu PrismJS selon [[13.04 Protection de la clé privée contre le XSS]] : texte brut coloré par `Prism.highlight`, inséré par le module de rendu.

Un testeur affecté après la création reçoit la CEK par le flux de partage de la GED (ged-chiffree-5).

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
