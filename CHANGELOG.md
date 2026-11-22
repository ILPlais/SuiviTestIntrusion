# Historique des versions

Ce fichier suit le format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et les versions suivent [SemVer](https://semver.org/lang/fr/). La procédure est décrite dans [`41.01 Versionnage et procédure de livraison`](docs/40-49%20Publication/41%20Livraison/41.01%20Versionnage%20et%20proc%C3%A9dure%20de%20livraison.md).

## Non publié

### Ajouts

- Pile Docker Compose (`app`, `queue`, `scheduler`, base PostgreSQL optionnelle), exemples de proxy inverse nginx et Caddy, `TRUSTED_PROXIES`, SPA servie par Laravel sur la même origine que l'API ; documentation d'installation Docker (42.01) et manuelle (42.02) (socle-2).
- Limites de débit de l'API par classe (Standard, Écriture lourde, Export, Auth avec attente croissante), requêtes simultanées par utilisateur, réponse 429 avec `X-RateLimit-*` et `Retry-After`, corps JSON limités à 2 Mo (413) (socle-5).
- Organisations clientes (tenants) et rôles par organisation (chef de projet, testeur, client final) ; isolation multi-tenant : chaque lecture filtrée par les organisations de l'utilisateur, 404 hors organisation ; API GET /api/v1/tenants (identite-5).

### Modifications

- Documentation du code rendue obligatoire en CI : DocBlock décrit et typé sur chaque élément PHP (règle Larastan du projet, règles phpdoc_* de PHP-CS-Fixer), JSDoc sur chaque fonction JavaScript et TypeScript (eslint-plugin-jsdoc) ; code existant mis en conformité (21.01).

### Corrections

### Sécurité
