# Story: Références entreprise, testeurs et clients

**ID:** engagement-1
**Epic:** engagement
**Sprint:** 4
**Status:** in-progress
**Points:** 2

## Context

En tant que chef de projet, je veux gérer les fiches des acteurs d'un test.

## Acceptance Criteria

- [ ] CRUD entreprise prestataire, testeur, client (tenant)
- [ ] Présentation en texte riche

## Technical Notes

Dépend de identite-5.

Reporté de la revue de sécurité d'identite-5, à traiter avec les premiers objets métier :

- tenant_id jamais accepté depuis la requête : hors #[Fillable], affecté côté serveur depuis la route ou l'organisation courante ; interdire DB::table() sur les tables à tenant_id et withoutGlobalScope(s) hors de `App\Tenancy` (requêtes de masse update/insert/upsert qui contournent l'événement saving) ;
- policies par modèle appliquant TenantRole (client en lecture seule), un test par rôle et par verbe ;
- Tenant::users() et User::tenants() à restreindre par rôle avant toute exposition dans l'API ;
- mémoriser TenantAccess::visibleTenantIds() par requête (une requête SQL par application du filtre aujourd'hui) ;
- documenter que le rappel de withoutRestriction() doit matérialiser ses résultats.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
