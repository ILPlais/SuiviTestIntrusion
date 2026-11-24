# Historique des versions

Ce fichier suit le format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et les versions suivent [SemVer](https://semver.org/lang/fr/). La procédure est décrite dans [`41.01 Versionnage et procédure de livraison`](docs/40-49%20Publication/41%20Livraison/41.01%20Versionnage%20et%20proc%C3%A9dure%20de%20livraison.md).

## Non publié

### Ajouts

- Pile Docker Compose (`app`, `queue`, `scheduler`, base PostgreSQL optionnelle), exemples de proxy inverse nginx et Caddy, `TRUSTED_PROXIES`, SPA servie par Laravel sur la même origine que l'API ; documentation d'installation Docker (42.01) et manuelle (42.02) (socle-2).
- Limites de débit de l'API par classe (Standard, Écriture lourde, Export, Auth avec attente croissante), requêtes simultanées par utilisateur, réponse 429 avec `X-RateLimit-*` et `Retry-After`, corps JSON limités à 2 Mo (413) (socle-5).
- Organisations clientes (tenants) et rôles par organisation (chef de projet, testeur, client final) ; isolation multi-tenant : chaque lecture filtrée par les organisations de l'utilisateur, 404 hors organisation ; API GET /api/v1/tenants (identite-5).
- Fiches des références d'un test : organisations clientes (création et suppression par un administrateur, modification par le chef de projet), entreprise prestataire, profil du testeur (nom, poste, présentation) ; écrans Organisations, Entreprise et Mon profil ; présentations en Markdown, rendues dans le navigateur et assainies (engagement-1).
- Journal d'audit : auteur, horodatage, organisation, objet, action, résumé avant / après débarrassé des secrets ; connexions, double authentification, comptes, organisations, tests, vulnérabilités, remédiations et fiche entreprise consignés ; rétention paramétrable (`AUDIT_RETENTION_DAYS`, purge quotidienne) ; API GET /api/v1/audit-events réservée aux administrateurs d'instance (identite-6).
- Paire de clés OpenPGP v6 générée dans le Worker crypto à l'activation (openpgp.js, Argon2id, AEAD) ; clé privée stockée chiffrée en deux copies, par le mot de passe et par une clé de récupération de 128 bits affichée une seule fois et ressaisie avant l'activation ; déverrouillage à la connexion, empreinte et changement de mot de passe sans rechiffrer les fichiers dans l'écran Sécurité ; API GET /api/v1/user/vault et PUT /api/v1/user/password (ged-chiffree-1).
- Cycle de vie des comptes : invitations par un administrateur (tout rôle) ou un chef de projet (clients finaux de ses organisations), lien de 72 heures affiché à l'invitant, renvoi et annulation, compte actif rattaché sans nouvel enrôlement ; désactivation réversible (sessions et jetons révoqués, invitations émises annulées), suppression pseudonymisée d'un compte désactivé, dernier administrateur protégé, déverrouillage anticipé ; mot de passe oublié par un lien de 24 heures émis par un administrateur, avec la clé de récupération ; écran Utilisateurs et page de réinitialisation (identite-7).

### Modifications

- Lien d'activation valable 72 heures par défaut au lieu de 24 (`AUTH_ACTIVATION_HOURS`, 11.06) ; nouveau réglage `AUTH_RESET_HOURS` (identite-7).
- Documentation du code rendue obligatoire en CI : DocBlock décrit et typé sur chaque élément PHP (règle Larastan du projet, règles phpdoc_* de PHP-CS-Fixer), JSDoc sur chaque fonction JavaScript et TypeScript (eslint-plugin-jsdoc) ; code existant mis en conformité (21.01).

### Corrections

- Session conservée au rechargement de la SPA compilée : les requêtes de l'API envoient un Referer de même origine malgré `Referrer-Policy: no-referrer`, sans lequel Sanctum ignorait la session des GET (401) (ged-chiffree-1).

### Sécurité
