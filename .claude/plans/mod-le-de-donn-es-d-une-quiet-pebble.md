# Plan : modèle de données d'une « tâche » (12.01 §2.2)

**Complexité :** faible (documentation seule, aucun code)

## Contexte

Le rapport 12.01 §2.2 (🟠 Important) relève que la **tâche** n'est définie nulle part dans le CDC. Le CDC l'utilise pourtant dans *Planning* (dates prévues et réelles, temps par phase), *Collaboration* (attribution, commentaires), *ACL*, *Notifications*, *Objets du périmètre* et *MITRE ATT&CK*. La story deroulement-1 (« Phases et modèle de tâche ») attend ce modèle. Ce plan suit le schéma des commits 5543df5 et 1a15161 : section du CDC rédigée, point 12.01 marqué traité, stories alignées, un commit en français sur `main`.

Choix validés : **six statuts**, **plusieurs assignés**, **temps passé saisi** (pré-rempli), **résultat du test** distinct du statut. Écartés pour la V1 : modèles de tâches, dépendances, liste de contrôle.

## 1. CDC : `docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md`

### 1.1 Nouvelle section `## Tâches` (entre `## Phases` l.480 et `## Outils` l.490)

Contenu proposé :

- **Définition** : unité de travail d'une phase (test à mener, vérification, action de nettoyage), rattachée à une seule phase.
- **Champs** : référence (unique dans le test) ; titre ; description ; phase ; assignés (zéro, un ou plusieurs testeurs affectés au test ; sans assigné = **libre**) ; statut et motif ; résultat ; dates prévues et réelles (renvoi *Planning*) ; temps passé ; liens n-n vers objectifs, objets du périmètre, vulnérabilités, exécutions d'outils et techniques ATT&CK (renvoi *Annexes › MITRE ATT&CK*).
- **Secrets** : jamais dans la description ni les commentaires. Ils vont dans les secrets du périmètre ou dans la GED, chiffrés dans le navigateur (13.01).
- `### Statuts d'une tâche` : tableau statut / signification / transitions :

  | Statut | Transitions vers |
  | --- | --- |
  | À faire | En cours, Bloquée, Abandonnée |
  | En cours | Bloquée, Terminée, Abandonnée |
  | Bloquée (motif obligatoire : attente d'un accès, du client…) | À faire, En cours, Abandonnée |
  | Terminée | Vérifiée, En cours (réouverture) |
  | Vérifiée | En cours (réouverture, commentaire obligatoire) |
  | Abandonnée (motif obligatoire : hors périmètre, refus du client, manque de temps) | À faire |

  Le passage à *En cours* fixe le début réel, celui à *Terminée* la fin réelle, sauf si ces dates sont déjà saisies. Seul le chef de projet passe une tâche à *Vérifiée* ou rouvre une tâche vérifiée.
- `### Résultat` : Vulnérable (au moins une vulnérabilité liée exigée) ; Non vulnérable ; Non applicable (test mené, l'élément visé est absent) ; Non concluant (test mené sans conclusion). Le résultat est obligatoire pour passer à *Terminée*. Une phrase distingue *Abandonnée* (test non mené) de *Non applicable* (test mené).
- `### Temps passé` : durée pré-remplie par l'écart entre début et fin réels, modifiable par un assigné ou par le chef de projet. Elle mesure l'effort total de l'équipe sur la tâche, pas le calendrier.

### 1.2 Retouches ailleurs dans le CDC

- *Planning* l.523 : « somme des durées réelles des tâches » → « somme des **temps passés** des tâches de la phase (voir *Tâches*) ».
- *Collaboration* l.529 : ajouter que le chef de projet peut assigner plusieurs testeurs à une même tâche.
- *ACL › Chef de projet* l.381 : ajouter « Vérifier les tâches terminées ; ».
- *Exports › Technique* l.209 : ajouter « tâches et leur résultat (les tâches abandonnées figurent comme non testées, avec leur motif) ».
- *Migration* l.227 : « objectifs » → « objectifs, phases et tâches ».

## 2. Rapport 12.01 : `docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md`

- Après le §2.2 (l.91), ajouter `**Traité** (2026-11-09) : …` résumant la nouvelle section, avec `[[11.01 Cahier des charges]]` et les stories concernées. Ajouter que le lien *Tâche ↔ objectif* du §2.4 est couvert, sans marquer le §2.4 comme traité.
- Tableau de synthèse l.311 : « Modèle de données d'une tâche (traité dans 11.01) », sur le modèle de la l.310.

## 3. Stories : via `ledger.js apply`, jamais à la main

Script : `node C:/Users/ILP/.claude/plugins/cache/ecc/ecc/2.2.3/skills/story-lifecycle/scripts/ledger.js apply --input <json> --root <repo>`. Le JSON `{ "update": { "<id>.md": "<contenu complet>" } }` est écrit dans le scratchpad, puis supprimé.

- **deroulement-1** : points 3 → 5 (story non planifiée, donc sans effet sur le sprint). Critères :
  - [ ] Cinq phases par défaut, désactivables et extensibles (inchangé)
  - [x] Modèle documenté dans le CDC (11.01, Objectifs › Tâches)
  - [ ] Champs : référence, titre, description, phase, assignés, statut et motif, résultat, dates, temps passé
  - [ ] Six statuts et transitions ; motif obligatoire pour Bloquée et Abandonnée ; Vérifiée réservée au chef de projet
  - [ ] Résultat obligatoire pour passer à Terminée ; « Vulnérable » exige une vulnérabilité liée
  - [ ] Liens n-n vers objectifs, objets du périmètre, vulnérabilités, exécutions d'outils

  Notes : renvoi à la section CDC. ATT&CK reste dans vulnerabilites-7, les exécutions dans deroulement-6. Un lien est livré avec la story de l'entité cible si celle-ci n'existe pas encore.
- **deroulement-2** : « Attribution par le chef de projet ou auto-attribution » → deux critères : « Attribution par le chef de projet, un ou plusieurs assignés » et « Auto-attribution d'une tâche libre (sans assigné) ».
- **deroulement-3** : le critère « Temps par phase calculé… » devient « Temps passé par tâche pré-rempli par l'écart des dates réelles, modifiable » et « Temps par phase = somme des temps passés, ajustement manuel avec commentaire obligatoire ».
- **exports-1** : ajouter en note que le modèle Technique liste les tâches avec leur résultat, et les tâches abandonnées comme non testées.

Puis lancer `ledger.js status --root <repo>` : attendu sans `drift` ni `recoveryPending`.

## 4. Session et commit

- Copier les notes de cette session, `~/.claude/session-data/2026-11-09-b9623aff-session.tmp` (ou le fichier du jour le plus récent), dans `.claude/session-data/`. C'est la règle de CLAUDE.md.
- Ajouter à l'index git **uniquement** : 11.01, 12.01, les 4 stories et la note de session. Ne pas inclure `.obsidian/*`, `CLAUDE.md` (modifié) ni `.stories.md` (vide, non suivi).
- Message de commit : `Modèle de données d'une tâche : CDC, 12.01 §2.2, stories deroulement-1/2/3 et exports-1.`, un corps qui résume les règles, puis la ligne `Co-Authored-By`.
- À signaler sans agir : la note `2026-11-09-017477e3-session.tmp` de la session précédente n'est pas encore copiée ni commitée.

## Vérification

- `grep -n "durées réelles des tâches"` sur 11.01 : aucun résultat.
- `grep -n "## Tâches\|Statuts d'une tâche\|### Résultat\|### Temps passé"` : les sections existent sous `# Objectifs`.
- `ledger.js status` : aucune dérive ; la table de l'epic `deroulement` est cohérente.
- `git diff --stat` avant le commit : seulement les 7 fichiers prévus. Relecture du diff du CDC : tableaux Markdown valides, termes au féminin (« Terminée »), liens Obsidian `[[…]]` existants.
- `git status` après le commit : il ne reste que les modifications non liées (`.obsidian`, `CLAUDE.md`, `.stories.md`).

## Risques

| Risque | Mitigation |
| --- | --- |
| Recouvrement *Abandonnée* / *Non applicable* | Phrase de distinction explicite dans le CDC |
| Le §2.4 (ERD) et le §2.8 (phases configurables) sont touchés en bordure | Mentionnés, non marqués traités |
| Écriture directe dans `.stories/` | Passer exclusivement par `ledger.js apply` |
