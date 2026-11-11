# Plan : report en V2 de la validation formelle de l'engagement (12.01 §2.10)

## Contexte

Le §2.10 du [[12.01 Rapport d'analyse du CDC]] relève que le questionnaire d'engagement n'a aucune **validation opposable** : pas de signature électronique, pas d'export PDF signable, pas d'horodatage certifié. La synthèse le marque deux fois « ⬜ Ouvert » : 🟠 « Validation formelle de l'engagement » et 🟢 V2 « Signature électronique de l'engagement ».

Décision de Lionel (2026-11-09) :
- **V1 : jalon seul.** Le jalon « Kick-off / signature de l'engagement » (CDC, *Planning*, l. 563), daté et coché par le chef de projet, trace l'acceptation. La signature a lieu hors de l'application. Pas de dépôt imposé dans la GED, pas d'avertissement au passage « En cours ».
- **V2 :** toute la validation formelle, suivie par une nouvelle story **engagement-8** (sprint non assigné, note « Reportée en V2 »), comme `identite-2` pour LDAP/AD.

Ce changement porte uniquement sur la spécification et le backlog : aucun code.

## Modifications

### 1. CDC — [11.01 Cahier des charges.md](docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md)

Nouvelle sous-section **`## Validation de l'engagement`** à la fin de `# Questionnaire d'engagement`, après « Contacts d'urgence » (l. 325) et avant `# Gestion des utilisateurs`. Contenu (en suivant le style du paragraphe LDAP, l. 333) :
- En V1, l'acceptation de l'engagement est tracée par le jalon « Kick-off / signature de l'engagement » (voir *Planning*), daté et coché par le chef de projet ; la lettre d'engagement est signée hors de l'application.
- Reporté en V2 : signature électronique conforme eIDAS de la lettre d'engagement par le client et le prestataire ; export PDF figé et signable du questionnaire rempli ; horodatage qualifié de l'acceptation.
- Le niveau de signature (avancée ou qualifiée), le prestataire de confiance et le mode d'horodatage seront décidés dans un nouvel ADR.

Le CDC ne cite aucune story : pas de référence à engagement-8 dans ce texte.

### 2. Rapport — [12.01 Rapport d'analyse du CDC.md](docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md)

- **§2.10** (après la l. 153) : ligne `**Traité** (2026-11-09) : …` dans le format des §1.2 et §2.2. Elle indique que la section *Questionnaire d'engagement › Validation de l'engagement* du [[11.01 Cahier des charges]] reporte la validation formelle en V2 (les trois points du §2.10), qu'en V1 seul le jalon trace l'acceptation, et elle cite la story engagement-8 (V2).
- **Synthèse** : les deux lignes 2.10 (l. 318 et l. 325) passent de `⬜ Ouvert` à `⏭️ Reporté en V2 : [[11.01 Cahier des charges]]`. Garder une **espace insécable** (U+00A0) après l'emoji, comme dans les autres cellules de la colonne. Les priorités ne changent pas.

### 3. Backlog — via `ledger.js` uniquement

Script : `C:/Users/ILP/.claude/plugins/cache/ecc/ecc/2.2.3/skills/story-lifecycle/scripts/ledger.js`, `--root .` (état actuel : 62 stories, pas de drift).

1. `allocate --epic engagement --count 1` → ID attendu `engagement-8` (utiliser celui que renvoie la commande).
2. Un seul `apply --input <scratchpad>/batch.json`, puis supprimer le JSON :
   - **create `engagement-8.md`** : « Validation formelle de l'engagement », Sprint `unassigned`, Status `todo`, Points `8`.
     - Contexte : en tant que chef de projet, je veux faire signer l'engagement par le client avec une valeur probante, depuis l'application (12.01 Rapport d'analyse du CDC §2.10).
     - Critères : export PDF figé et signable du questionnaire rempli et de la lettre d'engagement ; signature électronique conforme eIDAS, avec un niveau et un prestataire fixés par ADR ; horodatage qualifié et empreinte du document signé conservée ; document signé rangé dans la GED et lié au test, jalon « Kick-off / signature » coché à la date de signature ; signature, refus et expiration audités ; module désactivable (instance sans accès Internet).
     - Notes techniques : dépend de engagement-3 et exports-2. Reportée en V2 : en V1, seul le jalon trace l'acceptation ([[11.01 Cahier des charges]]). Point à trancher dans l'ADR : le document signé revient du prestataire en clair, ce qui contredit le principe « le serveur ne voit jamais le clair » de la GED. Il faut soit le récupérer et le chiffrer dans le navigateur, soit l'exclure explicitement des preuves chiffrées.
     - Definition of Done standard.
   - **update `epics/engagement.md`** : sous « Hors périmètre », remplacer `Signature électronique (V2)` par `Validation formelle de l'engagement : signature électronique, PDF signable, horodatage qualifié (V2, story engagement-8 conservée)` (modèle : `epics/identite.md`). Le tableau des stories est régénéré par le batch.
   - **update `epics/exports.md`** : sous « Hors périmètre », remplacer `Signature électronique de l'engagement (V2)` par `Signature électronique de l'engagement (V2, story engagement-8)`.
3. `decisions-6` n'est pas modifiée : son critère Roadmap cite déjà la « signature électronique ».

### 4. [CLAUDE.md](CLAUDE.md)

`10 epics, 61 stories` → `10 epics, 63 stories`. Le ledger compte déjà 62 stories, la mention était donc périmée ; engagement-8 porte le total à 63.

### 5. Session et commit

- Copier la note de session ECC de cette session (`~/.claude/session-data/2026-11-09-df4762e4-session.tmp`, ou la plus récente du jour) dans `.claude/session-data/`.
- Un commit en français sur `main`, avec uniquement les fichiers ci-dessus. Exclure `.obsidian/*` et `.stories.md`, qui sont des modifications sans rapport. Message proposé :
  `Validation formelle de l'engagement reportée en V2 : CDC, 12.01 §2.10, story engagement-8.` Corps : détail des choix, puis `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

`CHANGELOG.md` n'est pas modifié : les commits de spécification précédents n'y ajoutent rien.

## Vérification

- `node <ledger.js> status --root .` : 63 stories, `drift: []`, `recoveryPending: false` ; engagement-8 en `todo` / `unassigned` / 8 points.
- `grep -nP '⏭️\x{00A0}' "…/12.01 Rapport d'analyse du CDC.md"` → deux lignes.
- Le titre `## Validation de l'engagement` du CDC se trouve entre `# Questionnaire d'engagement` et `# Gestion des utilisateurs` (grep des titres).
- `git diff --stat` et `git status` : seuls le CDC, le 12.01, `.stories/engagement-8.md`, les deux epics, `CLAUDE.md` et la note de session sont modifiés.
