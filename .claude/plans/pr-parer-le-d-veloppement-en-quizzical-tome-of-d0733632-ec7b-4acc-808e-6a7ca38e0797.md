# Plan : diagramme de Gantt du développement (22.01)

## Contexte

Le backlog `.stories/` compte 69 stories (230 points, 217 restants) réparties en 10 epics, avec des dépendances écrites dans le texte de chaque story (« Dépend de … »). Seul le sprint 1 existe (2026-10-27 → 2026-11-09). Il a livré 13 points sur 27, et 6 stories restent ouvertes (decisions-4, 5 et 6, socle-1, 3 et 4). Aucune vue d'ensemble ne montre l'ordre de réalisation ni une date de fin. But : un Gantt du **projet** (et non la vue Gantt de l'application, story deroulement-3), lisible dans Obsidian, pour préparer le développement.

Choix validés : capacité de **15 points par sprint de 2 semaines** ; nouveau document **22.01** (catégorie « 22 Planification ») avec un diagramme **Mermaid gantt** ; **stories groupées par epic**.

## Approche

### 1. Extraire le graphe des stories (script jetable dans le scratchpad)

Script Node `gantt-22.01.mjs` dans le scratchpad, en lecture seule sur `.stories/` :
- lit `ID`, `Epic`, `Points`, `Status` et le titre de chaque story (fichiers CRLF → normalisés en LF à la lecture) ;
- extrait les dépendances des phrases « Dépend de … » (identifiants `<epic>-<n>`, plage « decisions-1 à decisions-5 » développée) ;
- vérifie que tous les identifiants existent et que le graphe n'a pas de cycle.

### 2. Ordonnancer

- Stories `done` exclues du Gantt détaillé (mentionnées comme réalisées au sprint 1).
- Tri topologique ; à égalité : stories reportées du sprint 1, puis ordre des epics (decisions → socle → identite → ged-chiffree → engagement → vulnerabilites → deroulement → resultats → exports → exploitation), puis numéro.
- **Stories reportées en V2** (mention « Reportée en V2 » dans la story) : identite-2 (3), engagement-8 (8), deroulement-9 (8), soit 19 points. Elles sont placées **après** le jalon V1, dans une section « V2 (reportées) » du Gantt détaillé. V1 = 198 points restants, environ 13,2 sprints.
- Placement en flux continu de points : une story occupe l'intervalle [c, c + p) du cumul de points ; le sprint k couvre [15(k-1), 15k) ; le jour ouvré dans le sprint vaut ⌊(x mod 15) × 10 / 15⌋. Dates de début et de fin explicites calculées par le script (pas de `after`), au minimum 1 jour ouvré. Les dépendances sont respectées par construction (tri topologique sur un flux unique).
- Mermaid : `dateFormat YYYY-MM-DD`, `excludes weekends` ; identifiants de tâche avec `_` au lieu de `-` ; « : » et « ; » retirés des titres.
- Sprints : sprint 2 à partir du lundi 2026-11-23, puis tous les 14 jours.
- Jalons : fin des ADR, socle prêt, fin de chaque epic, **V1 (1.0.0)** après la dernière story V1, puis fin des stories V2.

### 3. Rédiger `docs/20-29 Développement/22 Planification/22.01 Planification du développement.md`

Style de 21.01 (espaces normales, LF). Contenu : hypothèses ; Gantt synthétique par epic ; Gantt détaillé (une section par epic, une barre par story, stories reportées en `active`) ; tableau par sprint ; chemin critique et risques ; méthode de mise à jour. Le document est un prévisionnel : aucune écriture dans `.stories/`.

### 4. Références

- `00.00 Index` : ajouter la catégorie 22 et 22.01.
- `CLAUDE.md` § Documents : citer `22.01`.

### 5. Livraison

Commit sur `main` (documentation), copie du plan dans `.claude/plans/`, `git push origin main` en fin de session.

## Vérification

- Script : 63 stories planifiées (60 V1 pour 198 points, 3 V2 pour 19 points), aucun identifiant inconnu, aucun cycle, début(A) ≥ fin(B) pour chaque dépendance.
- Rendu Mermaid validé avec mermaid-cli si disponible, sinon dans Obsidian.
- Liens `[[…]]` : 0 cassé ; `ledger.js status` : drift `[]`.
