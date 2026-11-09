# Story: Validation formelle de l'engagement

**ID:** engagement-8
**Epic:** engagement
**Sprint:** unassigned
**Status:** todo
**Points:** 8

## Context

En tant que chef de projet, je veux faire signer l'engagement par le client avec une valeur probante, depuis l'application (12.01 Rapport d'analyse du CDC §2.10).

## Acceptance Criteria

- [ ] Export PDF figé et signable du questionnaire rempli et de la lettre d'engagement
- [ ] Signature électronique conforme eIDAS par le client et le prestataire ; niveau (avancée ou qualifiée) et prestataire de confiance fixés par un ADR
- [ ] Horodatage qualifié de l'acceptation ; empreinte du document signé conservée
- [ ] Document signé rangé dans la GED et lié au test ; jalon « Kick-off / signature de l'engagement » coché à la date de signature
- [ ] Signature, refus et expiration de la demande audités
- [ ] Module désactivable (instance sans accès Internet)

## Technical Notes

Dépend de engagement-3 et exports-2.

Reportée en V2 : en V1, seul le jalon « Kick-off / signature de l'engagement », daté et coché par le chef de projet, trace l'acceptation ; la lettre est signée hors de l'application ([[11.05 Engagement]], Validation de l'engagement).

Point à trancher dans l'ADR : le document signé revient du prestataire de signature en clair, ce qui contredit le principe de la GED selon lequel le serveur ne voit jamais le clair. Il faut soit le récupérer et le chiffrer dans le navigateur, soit l'exclure explicitement des preuves chiffrées.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
