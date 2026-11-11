# Plan : traiter le §4.4 « Protection XSS et clé privée » du rapport 12.01

## Contexte

Le §4.4 de [12.01 Rapport d'analyse du CDC](docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md) est le dernier point 🟠 encore ouvert. La clé privée OpenPGP déchiffrée vit en mémoire JavaScript : un XSS réussi la compromet aussitôt. Aujourd'hui, le CDC n'en parle que dans une ligne des *Points de vigilance* de 11.11 (« Mémoire côté client »). 13.01 conclut seulement qu'une « CSP stricte reste indispensable ».

Ce qui existe déjà :
- **Aucune story ne cite le §4.4.**
- `decisions-4` (ADR infrastructure, §3.2–3.5) a un critère « Politique CSP stricte, CORS, TLS/HSTS et flags de cookies Sanctum ».
- `socle-6` implémente « CSP sans `unsafe-inline` ».
- `ged-chiffree-2` couvre la durée de vie de la clé en mémoire (§4.3).

Le risque est propre à cet outil : les données saisies (descriptions de vulnérabilités, sorties d'outils, logs, preuves) **contiennent normalement des charges XSS**. Tout contenu doit donc être traité comme hostile.

Choix validés avec l'utilisateur :
1. Créer un **ADR 13.04** et une **nouvelle story `decisions-7`**.
2. Isoler la clé dans un **Web Worker dédié**.
3. Faire une **preuve de faisabilité dans un vrai navigateur**, avec une mini SPA.

Résultat attendu : le §4.4 passe à ✅ dans la synthèse de 12.01. La décision est reportée dans le CDC et dans les stories d'implémentation.

## Étapes

> Hook GateGuard : avant le premier Bash et avant chaque création de fichier, énoncer les faits demandés. Toute écriture dans `.stories/` passe par `node C:\Users\ILP\.claude\plugins\cache\ecc\ecc\2.2.3\skills\story-lifecycle\scripts\ledger.js <cmd> --root <racine>`. Les JSON d'entrée sont placés dans le scratchpad, puis supprimés.

### 1. Ouvrir la story `decisions-7`
- `ledger.js allocate --epic decisions --count 1`, puis `apply` pour créer `decisions-7.md` au statut `todo`. Dans le même lot, ajouter « protection XSS de la clé privée » au Scope de `epics/decisions.md`.
- Contenu de la story :
  - titre « ADR : protection de la clé privée contre le XSS » ;
  - Sprint `1`, comme les autres stories `decisions`, car il n'existe pas de sprint 2 ; Points `3`, comme decisions-1 et decisions-3, qui comportaient aussi une preuve ;
  - Context : « En tant qu'architecte, je veux protéger la clé privée déchiffrée contre un XSS (12.01 … §4.4, et la CSP du §3.2) » ;
  - critères : politique CSP (SPA, API, Swagger UI) ; isolation dans le Worker crypto (opérations exposées, ce qui n'en sort jamais) ; règles de rendu des contenus hostiles ; limites résiduelles ; preuve navigateur consignée ;
  - Technical Notes : « Dépend de decisions-1, decisions-3 ».
- Un second `apply` fait passer la story de `todo` à `in-progress`. Le ledger n'accepte qu'un pas en avant à la fois.

### 2. Preuve de faisabilité (scratchpad, hors dépôt)
- Créer le projet dans un dossier neuf `…\scratchpad\poc-csp-13.04\` : Vite, Vue 3, Pinia, vue-i18n, openpgp 6.1.1 (même version que 13.01), argon2id 1.0.1 (13.03), Mermaid, PrismJS et DOMPurify. Toutes les versions sont épinglées et seront reportées dans l'ADR.
- Écrire un petit serveur Node dans un dossier séparé `…\scratchpad\poc-server\`. Il sert le build avec l'en-tête CSP cible. Il simule aussi une réponse JSON d'API et un blob (en-têtes de l'API), et reçoit les rapports de violation.
- Navigateur : Chrome via les outils `chrome-devtools` (navigation, console, `evaluate_script`). Ajouter Firefox s'il est disponible, et noter les navigateurs réellement testés.
- **Cas qui doivent passer sans aucune violation CSP** :
  - montage de Vue, message vue-i18n, store Pinia ;
  - Worker module qui calcule `auth` (Argon2id WASM), génère une clé v6, la déverrouille (S2K), puis chiffre et déchiffre 1 Mio ;
  - diagramme Mermaid en `securityLevel: 'strict'` ;
  - bloc PrismJS rendu à travers la politique Trusted Types ;
  - aperçu d'une image PNG déchiffrée via `blob:`.
- **Cas qui doivent être bloqués** :
  - `<script>` et `<img onerror>` injectés ;
  - `eval` ;
  - `fetch` vers une autre origine ;
  - script externe ;
  - affectation de `innerHTML` hors de la politique (Trusted Types, selon le navigateur) ;
  - libellé Mermaid et sortie d'outil contenant une charge XSS : ils doivent s'afficher comme du texte.
- **Contre-épreuves**, pour justifier chaque directive :
  - sans `'wasm-unsafe-eval'`, argon2id doit échouer ;
  - Mermaid sous `style-src 'self'` seul : vérifier s'il fonctionne. Si ce n'est pas le cas, autoriser `'unsafe-inline'` **pour les styles uniquement** et le justifier dans l'ADR ;
  - contrôler que le build Vite ne contient aucun script inline.
- Isolation de la clé : vérifier qu'aucune matière de clé n'est accessible depuis le fil principal (store, `window`), et qu'après `worker.terminate()` toute demande de déchiffrement échoue.

### 3. Rédiger l'ADR 13.04 à partir du modèle 01.01
Fichier : `docs/10-19 Conception/13 Décisions d'architecture/13.04 Protection de la clé privée contre le XSS.md`. Frontmatter `statut: accepté`, `story: decisions-7`. Le style suit 13.01 à 13.03 : tableaux en gras, liens `[[…]]`, phrases courtes.

**Contexte.** Il reprend :
- le §4.4 et la CSP du §3.2 ;
- la conclusion de 13.01 ;
- la clé et le mot de passe manipulés dans le navigateur (13.03) ;
- les contenus hostiles par nature ;
- la SPA statique servie par le service `web` (11.02) ;
- les contraintes WASM, Mermaid, PrismJS et Swagger UI.

**Options.**
1. CSP par liste blanche `'self'`, en-tête statique.
2. CSP à nonce avec `'strict-dynamic'`, ce qui impose de générer `index.html` à chaque requête.
3. Les mêmes options, avec ou sans Worker. L'utilisateur a retenu le Worker.

**Décision.** Option 1. Il n'y a aucun script inline, et un nonce imposerait un `index.html` dynamique. Ce choix tient à une condition : l'origine ne sert aucun contenu interprétable comme script. L'ADR la garantit par les en-têtes de l'API.

Le contenu est ajusté selon la preuve :
- **CSP de la SPA** (tableau directive / valeur / raison) :
  - `default-src 'none'` ;
  - `script-src 'self' 'wasm-unsafe-eval'` ;
  - `style-src 'self'` (+ `'unsafe-inline'` si la preuve l'impose) ;
  - `img-src 'self' blob:` ;
  - `font-src`, `connect-src` et `worker-src` à `'self'` ;
  - `object-src 'none'`, `base-uri 'none'`, `form-action 'self'`, `frame-src 'none'`, `frame-ancestors 'none'` ;
  - `require-trusted-types-for 'script'` et `trusted-types` (noms des politiques) ;
  - `report-to`, vers un point de réception `/api/v1/csp-reports` limité en débit et en taille.

  La CSP est posée par le serveur web (configuration Docker). La configuration est documentée pour l'installation manuelle.
- **API** :
  - `default-src 'none'; frame-ancestors 'none'; sandbox` ;
  - `X-Content-Type-Options: nosniff` ;
  - blobs en `application/octet-stream` avec `Content-Disposition: attachment` ;
  - aucun JSONP.
- **Swagger UI** : CSP propre, avec un nonce posé par Laravel ; page réservée aux administrateurs et désactivable, comme déjà prévu.
- **Worker crypto** : un seul Worker module charge openpgp.js et argon2id ; le fil principal ne les importe pas.
  - Messages : calcul de `auth`, déverrouillage, verrouillage, chiffrement et déchiffrement de fichier, partage de CEK, rotation et récupération (13.02 et 13.03).
  - Ce qui ne sort **jamais** du Worker : la clé privée déchiffrée, les CEK, le mot de passe une fois utilisé.
  - Ce qui en sort : `auth`, les paquets chiffrés et le clair des fichiers à afficher.
  - Verrouiller revient à arrêter le Worker avec `terminate()` ; les durées relèvent du §4.3 et de ged-chiffree-2.
- **Rendu** :
  - interpolation Vue et `textContent` uniquement ; `v-html` interdit ;
  - `innerHTML` et équivalents seulement dans un module de rendu unique, avec une politique Trusted Types qui assainit le contenu avec DOMPurify ;
  - règles ESLint `vue/no-v-html`, `no-eval`, `no-implied-eval`, `no-new-func` ;
  - PrismJS : `Prism.highlight` sur du texte brut ;
  - Mermaid : `securityLevel: 'strict'` ;
  - vue-i18n : messages sans HTML.
- **Aperçus des preuves déchiffrées** :
  - affichage en ligne réservé aux images PNG, JPEG, WebP et GIF (`<img>` + `blob:`) et au texte (`textContent`) ;
  - SVG, HTML, XML, PDF et les autres types sont téléchargés uniquement, en `application/octet-stream` ;
  - on ne navigue jamais vers une URL `blob:` de contenu déchiffré, car elle hérite de l'origine.
- **Dépendances** : tout est servi depuis l'origine, sans CDN ; versions épinglées, lockfile, `npm audit` en CI.
- **Export HTML** : contenu échappé et CSP en `<meta>` qui interdit tout script.

**Limites résiduelles.**
- Un XSS qui passe malgré tout peut utiliser le Worker comme oracle pendant la session.
- Il peut aussi exfiltrer par navigation, que la CSP ne couvre pas.
- Ces mesures ne protègent ni contre une extension malveillante, ni contre un poste compromis, ni contre un serveur compromis qui servirait un code modifié (même limite de confiance que la distribution des clés publiques, 11.11).
- La prise en charge de Trusted Types varie selon les navigateurs (constat de la preuve) ; DOMPurify reste la barrière là où Trusted Types manque.

**Preuve de faisabilité.** Date, versions, navigateurs, résultats des cas passants, bloqués et des contre-épreuves.

**Conséquences.** CDC, API, front-end, back-end, stories, sécurité.

### 4. Reporter la décision dans le CDC
- **[11.11 Documents et chiffrement](docs/10-19 Conception/11 Cahier des charges/11.11 Documents et chiffrement.md)** :
  - nouvelle section `## Protection contre le XSS`, placée avant `## Points de vigilance`. Elle tient en une dizaine de lignes : pourquoi, CSP, Worker, règles de rendu, aperçus, limites, et un lien vers 13.04 ;
  - le point « Mémoire côté client » renvoie au Worker et à cette section. Le §4.3 reste ouvert.
- **[11.03 Serveur et API REST](docs/10-19 Conception/11 Cahier des charges/11.03 Serveur et API REST.md)**, section *Documentation OpenAPI* : une phrase sur la CSP propre de Swagger UI et sur les en-têtes de sécurité de l'API (13.04).
- **[11.04 Exports et modèles de rapport](docs/10-19 Conception/11 Cahier des charges/11.04 Exports et modèles de rapport.md)** : après la ligne 3, une phrase sur le rapport HTML (contenu échappé, scripts interdits, charges XSS fréquentes dans les constats).
- **[11.01 Cahier des charges](docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md)** : ajouter « protection contre le XSS » à la description de la ligne 11.11 du sommaire.

### 5. Mettre à jour 12.01 et les index
- **12.01 §4.4** : ajouter un paragraphe `**Traité** (2026-11-11) : …` au format des §1.x et §2.x. Il résume 13.04 et précise trois points :
  - la CSP du §3.2 est couverte ;
  - CORS, TLS et les cookies restent à decisions-4 ;
  - le §4.3 reste ouvert.

  Il cite la story decisions-7.
- **Synthèse de 12.01** : la ligne « CSP stricte et XSS / clé privée » passe de `🔲 Ouvert` à `✅ [[13.04 Protection de la clé privée contre le XSS]]`, avec une **espace insécable** après l'emoji (convention de CLAUDE.md, présente dans le tableau). Les reformatages de tableau non commités de l'utilisateur dans 12.01 sont conservés.
- **[13.00 Index des ADR](docs/10-19 Conception/13 Décisions d'architecture/13.00 Index des ADR.md)** : ajouter la ligne `| 13.04 | [[13.04 …]] | accepté | decisions-7 |`. 00.00 ne liste pas les ADR un par un : rien à modifier.

### 6. Mettre à jour les stories d'implémentation (un seul lot `apply`)
| **Story** | **Modification** |
| --- | --- |
| decisions-4 | Le critère CSP devient « CORS, TLS/HSTS et flags de cookies Sanctum (CSP : 13.04) » |
| socle-3 | Critère ajouté : règles ESLint de 13.04 bloquantes en CI |
| socle-6 | Critère CSP renvoyé à 13.04 (SPA et API, Trusted Types, rapports) ; critère E2E : aucune violation CSP et charges XSS affichées comme texte ; dépend aussi de decisions-7 |
| identite-1 | Note : le « module crypto unique » de 13.03 est le Worker de 13.04 |
| ged-chiffree-1 | Critère : clé privée, CEK et mot de passe confinés dans le Worker ; dépend de decisions-7 |
| ged-chiffree-2 | Note : verrouiller revient à arrêter le Worker (13.04) |
| ged-chiffree-4 | Critère : aperçu en ligne limité aux images matricielles et au texte, autres types téléchargés |
| deroulement-6 | Note : rendu PrismJS selon 13.04 |
| engagement-6 | Note : Mermaid en `securityLevel: 'strict'`, rendu selon 13.04 |
| exports-2 | Critère : rapport HTML échappé, scripts interdits par CSP |

Ensuite, faire passer `decisions-7` de `in-progress` à `review`, puis à `done`, en cochant les critères et la DoD. Ajouter dans ses Technical Notes la ligne « Pas de code pour un ADR : la preuve de faisabilité tient lieu de test », comme dans decisions-3. Le dernier `apply` régénère la ligne du tableau de l'epic et celle du sprint 1.

### 7. Commit et export de session
- Commit en français sur `main` : « Story decisions-7 : ADR 13.04, protection de la clé privée contre le XSS. », avec la ligne Co-Authored-By.
- Fichiers à indexer : 13.04, 13.00, 11.01, 11.03, 11.04, 11.11, 12.01 (qui inclut les reformatages de tableau de l'utilisateur), et `.stories/` (story, epic, sprint).
- **Exclus** : `.obsidian/*` et `12.03 Revue Cursor.md`, modifiés hors de cette tâche.
- Export de session : `/ecc:save-session`, puis copie dans `.claude/session-data/` et commit séparé (consigne de CLAUDE.md).

## Vérification
- **Preuve** : les résultats consignés dans l'ADR correspondent à ce qui a été observé dans la console : 0 violation sur les cas passants, blocage effectif des cas négatifs.
- **Ledger** : `ledger.js status` signale `drift` vide et `recoveryPending: false`, avec `decisions-7` à `done`.
- **Liens** : un script Node dans le scratchpad contrôle que chaque `[[…]]` de `docs/` et `.stories/` (ancres de titre comprises) pointe vers un fichier existant.
- **Espaces insécables** : `grep` sur U+00A0 après ✅ dans la nouvelle ligne de la synthèse de 12.01.
- **`git diff --stat` avant le commit** : seuls les fichiers listés à l'étape 7 apparaissent.
