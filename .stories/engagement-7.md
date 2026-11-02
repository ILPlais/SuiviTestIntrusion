# Story: Périmètre applicatif et ressources

**ID:** engagement-7
**Epic:** engagement
**Sprint:** unassigned
**Status:** todo
**Points:** 5

## Context

En tant que chef de projet, je veux déclarer les applications et les ressources cloud et SaaS du périmètre (12.01 Rapport d'analyse du CDC §2.1).

## Acceptance Criteria

- [ ] Applications web, API et mobiles : URL ou identifiant de paquet, version, environnement, plateformes, documentation fournie, fonctions exclues
- [ ] Comptes de test par rôle, chiffrés dans le navigateur (13.01)
- [ ] Ressources cloud et SaaS : fournisseur, compte / abonnement / projet, tenant d'identité, cluster Kubernetes, registre, chaîne CI/CD, service SaaS ; régions et services inclus ou exclus ; accès fourni ; contraintes de coût
- [ ] Autorisation du fournisseur ou de l'éditeur tracée (référence, date) ; alerte si elle manque au passage du test « En cours »
- [ ] Lien entre une application et son hébergement (machine ou ressource cloud)
- [ ] Sous-modules activables par test, comme ceux de engagement-5

## Technical Notes

Dépend de engagement-4 (objets du périmètre communs). Champs : CDC « Périmètre › Applications » et « Périmètre › Ressources cloud et SaaS ». Les méthodologies OWASP WSTG et MASTG relèvent de engagement-6.

## Definition of Done

- [ ] Tests written first cover the acceptance criteria
- [ ] Code review and verification loop passed
- [ ] Code merged to main
