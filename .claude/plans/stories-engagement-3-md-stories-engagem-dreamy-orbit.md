# Plan : périmètre applicatif et ressources (engagement-3, -4, -5, nouvelle -7)

## Contexte

Le CDC (11.01) ne décrit qu'un périmètre **réseau** (« Périmètre › Réseau » : machines, services, utilisateurs). Le rapport 12.01 §2.1 (🟠 Important) relève que les applications web/API/mobiles et les ressources cloud ne figurent **ni dans le questionnaire ni dans le périmètre**. Les stories engagement-3/4/5 héritent de ce trou : engagement-5 cite « web/API, cloud » en une ligne, sans détail, et le questionnaire n'a aucune question applicative ou cloud.

Aucun code n'existe (socle-1, identite-5, engagement-1/2 non faits) : ce travail porte sur la **spécification et les stories**. Le code suivra avec `story-lifecycle implement`.

Choix validés :
- Applicatif = **web, API et mobile** (cohérent avec OWASP WSTG / MASTG d'engagement-6).
- Ressources = **cloud IaaS/PaaS + identité + conteneurs + SaaS**.
- Découpage : nouvelle story **engagement-7** « Périmètre applicatif et ressources » ; engagement-5 garde physique, radio et ingénierie sociale.

## 1. CDC — `docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md`

### 1a. Questionnaire d'engagement (l. 252-307)

- l. 259 : « (Site physique + plage d'IP) » → « (sites physiques, plages d'IP, applications, ressources cloud et SaaS) ».
- Après « Questions systèmes et réseaux », ajouter deux rubriques conditionnelles (affichées si la cible correspondante est dans le périmètre) :
  - **Questions applicatives** : applications web, API et mobiles concernées (URL, identifiant de paquet, version) ; environnement (production, préproduction, recette) et nature des données ; comptes de test fournis et rôles couverts (anonyme, utilisateur, administrateur) ; documentation fournie (OpenAPI, GraphQL, collection Postman, code source en WhiteBox) ; mobile : binaire fourni (APK/IPA) ou magasin, plateformes et versions minimales ; WAF / anti-bot / limite de débit (contournement ou liste blanche) ; fonctions exclues (paiement, envoi réel de courriels ou SMS, suppression de données) ; accord de l'hébergeur ou de l'éditeur.
  - **Questions sur les ressources cloud et SaaS** : fournisseurs (AWS, Azure, GCP, autre) et comptes / abonnements / projets ; régions et services inclus ou exclus ; nature du test (revue de configuration authentifiée ou exposition externe) et accès fourni (rôle en lecture seule, compte d'audit) ; tenants d'identité (Entra ID / Microsoft 365, Google Workspace) ; clusters Kubernetes, registres de conteneurs, chaînes CI/CD ; services SaaS tiers et autorisation de leur éditeur ; respect de la politique de test du fournisseur (préavis, services interdits, déni de service exclu) ; contraintes de coût (ressources facturées à l'usage, mise à l'échelle automatique).

### 1b. Section « Périmètre » (l. 399-407) réécrite en sous-modules atomiques

Introduction : chaque sous-module est **activable par test** ; les réponses du questionnaire proposent l'activation (ex. « Des réseaux Wi-Fi… ? » = oui → Radio), le chef de projet confirme.

- **Objets du périmètre (commun)** : chaque objet (machine, application, ressource, site, cible…) porte inclus/exclu, commentaire, plages horaires interdites, contraintes de disponibilité ; il peut être lié aux tâches et aux vulnérabilités (12.01 §2.4). Les **secrets** (mots de passe d'utilisateurs, comptes de test, clés d'accès) sont chiffrés dans le navigateur comme les preuves ([[13.01 Format de chiffrement des preuves]]) ; le serveur ne les voit jamais en clair.
- **Réseau** : contenu actuel conservé + diagramme Mermaid.
- **Applications** : type (web, API, mobile) ; nom ; URL ou identifiant de paquet ; version ; environnement ; plateformes (mobile) ; documentation fournie ; comptes de test par rôle ; fonctions exclues ; hébergement (lien vers une machine ou une ressource cloud).
- **Ressources cloud et SaaS** : fournisseur ; type (compte / abonnement / projet, tenant d'identité, cluster Kubernetes, registre, chaîne CI/CD, service SaaS) ; identifiant ; régions ; services inclus / exclus ; accès fourni ; autorisation du fournisseur ou de l'éditeur (référence, date) ; contraintes de coût.
- **Sites physiques**, **Radio (Wi-Fi, Bluetooth, RFID)**, **Ingénierie sociale** : listes courtes reprises des questions RedTeam et ingénierie sociale existantes (sites, étages, gardes, matériel autorisé ; réseaux et SSID ; adresses et numéros autorisés / interdits, nombre de personnes ciblées) — nécessaires pour clore §2.1.

Pas de nouveau tableau à emojis (sinon : espace insécable après chaque emoji, convention du dépôt).

## 2. Rapport 12.01 — `docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md`

- Sous §2.1 (après l. 75), selon le modèle des points déjà traités :
  `**Traité** (2026-11-02) : CDC 11.01 « Périmètre » découpé en sous-modules (réseau, applications, ressources cloud et SaaS, sites physiques, radio, ingénierie sociale) ; questionnaire complété. Stories engagement-4, engagement-5, engagement-7.`
- Synthèse (l. 305) : « Périmètre applicatif / physique / cloud » → « … (traité dans 11.01) », comme la ligne 1.1.

## 3. Stories — via `ledger.js` uniquement

Skill : `C:/Users/ILP/.claude/plugins/cache/ecc/ecc/2.2.3/skills/story-lifecycle/scripts/ledger.js`.

1. `node ledger.js status --root .` (contrôle de dérive avant écriture).
2. `node ledger.js allocate --epic engagement --count 1 --root .` → attendu `engagement-7`.
3. Écrire le lot JSON dans le scratchpad, puis `node ledger.js apply --input <json> --root .`, puis supprimer le JSON.

Contenu du lot :

| Fichier | Action | Changement |
| --- | --- | --- |
| `engagement-7.md` | create | Titre « Périmètre applicatif et ressources », todo, 5 points, sprint unassigned. Contexte : « En tant que chef de projet, je veux déclarer les applications et les ressources cloud et SaaS du périmètre (12.01 §2.1). » AC : applications web/API/mobiles (champs §1b) ; comptes de test par rôle chiffrés dans le navigateur ; ressources cloud, identité, conteneurs, SaaS (champs §1b) ; autorisation du fournisseur tracée, alerte si absente au passage « En cours » ; lien application ↔ hébergement ; sous-modules activables. Notes : dépend de engagement-4 (objets communs) ; méthodologies WSTG/MASTG dans engagement-6. |
| `engagement-3.md` | update | AC « Toutes les rubriques du CDC » précisé : + rubriques applicatives et ressources cloud/SaaS ; nouvel AC : les réponses proposent l'activation des sous-modules du périmètre (engagement-4, -5, -7). Points inchangés (5). |
| `engagement-4.md` | update | Nouvel AC : objets du périmètre communs (inclus/exclu, plages interdites, liens tâches/vulnérabilités) réutilisés par engagement-5 et -7 ; note « protection renforcée » remplacée par « secrets chiffrés dans le navigateur (13.01) ». Points 3. |
| `engagement-5.md` | update | Titre → « Périmètre étendu (physique, radio, ingénierie sociale) » ; AC sans « web/API, cloud » ; note : applicatif et ressources dans engagement-7. Points 3. |
| `epics/engagement.md` | update | Scope : + « Périmètre applicatif et ressources » ; tableau des stories régénéré par le ledger. |
| `vulnerabilites-3.md` | update | « Liens vers machines du périmètre » → « Liens vers objets du périmètre (machines, applications, ressources) ». Dépendance : engagement-4 (inchangée). |

Statuts inchangés (todo), aucune affectation de sprint.

## 4. Commit

Un commit en français sur `main`, limité aux fichiers ci-dessus (ne pas embarquer `.obsidian/`, `.stories/decisions-2.md`, `00.00 Index.md`, `.stories.md`, déjà modifiés hors de ce travail). Message : « Périmètre applicatif et ressources : CDC, 12.01 §2.1, stories engagement-3/4/5/7. » + ligne Co-Authored-By.

## Hors périmètre

- Décision §2.9 (remplissage partiel par le client) : reste dans engagement-3.
- Ajout d'une méthodologie cloud au catalogue (engagement-6) : non demandé.
- Code Laravel / Vue.

## Vérification

- `node ledger.js status --root .` : engagement-7 présente, aucune dérive, `recoveryPending` faux.
- `grep -n "engagement-7" .stories/epics/engagement.md` : ligne présente.
- `grep -n "Applications\|Ressources cloud" 11.01` : sous-sections présentes ; liens `[[13.01 …]]` résolus (nom de fichier exact).
- Relecture du rendu Obsidian des deux rubriques ajoutées (indentation par tabulations comme l'existant).
- `git diff --stat` avant commit : uniquement les fichiers listés.
