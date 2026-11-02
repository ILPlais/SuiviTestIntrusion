# Story: Authentification LDAP et Active Directory

**ID:** identite-2
**Epic:** identite
**Sprint:** unassigned
**Status:** todo
**Points:** 3

## Context

En tant qu'administrateur, je veux brancher l'annuaire de l'entreprise.

## Acceptance Criteria

- [ ] Fournisseurs LDAP et AD configurables
- [ ] Comportement du coffre de clés conforme à l'ADR LDAP/AD

## Technical Notes

Dépend de identite-1.

Reportée en V2 : LDAP et Active Directory sont exclus de la V1 ([[13.03 Authentification locale et coffre de clés]]). Le mot de passe d'un annuaire étant vérifié par le serveur, ces comptes auront besoin d'une passphrase de coffre distincte, à décider dans un nouvel ADR.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
