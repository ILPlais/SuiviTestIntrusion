# Story: Validation des fichiers et antivirus

**ID:** ged-chiffree-7
**Epic:** ged-chiffree
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant qu'exploitant, je veux bloquer les fichiers dangereux (12.01 Rapport d'analyse du CDC §4.5).

## Acceptance Criteria

- [ ] Liste MIME paramétrable vérifiée sur les magic bytes avant chiffrement
- [ ] Exécutables refusés
- [ ] Scan ClamAV (activé par défaut selon l'ADR), rejet audité
- [ ] Taille maximale paramétrable, 100 Mio par défaut, contrôlée avant chiffrement puis sur la taille chiffrée ([[13.06 Infrastructure, e-mail et sécurité HTTP]])

## Technical Notes

Dépend de ged-chiffree-3. Le scan d'un contenu chiffré impose un scan côté client ou avant chiffrement : à préciser.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
