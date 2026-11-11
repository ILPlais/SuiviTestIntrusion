# Plan : score global d'un test mixte CVSS v3.1 / v4.0 (12.01 §1.4)

## Contexte

Le CDC (11.01, l. 562) laisse choisir la version CVSS fiche par fiche et autorise un mélange v3.1 / v4.0 « signalé ». Il ne dit pas comment calculer le **risque global** dans ce cas. La section *Résultats › Risque global* (l. 914-925) suppose une seule échelle : maximum des scores de base, moyenne pondérée P1=4…P4=1. Les scores v3.1 et v4.0 ne sont pas comparables entre eux. Le rapport 12.01 §1.4 demande donc une règle d'agrégation. La story `vulnerabilites-6` (todo, 1 pt) en dépend (critère « Règle documentée dans le CDC »).

Il s'agit d'un travail de **spécification seulement** (aucun code n'existe). On suit le modèle du commit `1a15161` : CDC + note « Traité » dans 12.01 + stories alignées, sans ADR, car c'est une règle fonctionnelle.

## Règle retenue (validée avec l'utilisateur)

- **Principe** : aucune comparaison ni aucun calcul numérique entre des scores v3.1 et v4.0. Seuls les **niveaux** (Aucun → Critique, mêmes seuils pour les deux versions) se comparent.
- **Test mixte** : test dont les vulnérabilités ouvertes (statut ≠ Vérifié, même base que les autres indicateurs) utilisent les deux versions. Si une seule version est présente, l'affichage reste celui d'aujourd'hui.
- **Bandeau (score de gravité technique)** : on affiche le **niveau le plus élevé** de toutes les fiches ouvertes, puis le **maximum numérique de chaque version séparément**, chacun avec son niveau (ex. « 🔴 Critique — v3.1 : 8,8 (Majeur) · v4.0 : 9,3 (Critique) »), et un **badge « Test mixte »**.
- **Score agrégé optionnel** : une **moyenne pondérée par version**, chacune avec son nombre de fiches. Il n'y a jamais de pondération croisée.
- **Inchangés** (ils reposent sur les niveaux ou les priorités) : les compteurs par niveau, phase, statut et priorité, le profil d'impact business, la matrice sévérité × impact et la suggestion de priorité P1–P4.
- **Rapports exportés** : les mêmes règles s'appliquent dans les chapitres de synthèse. Chaque score cité porte sa version.

## Fichiers à modifier

| Fichier | Action |
|---|---|
| `docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md` | l. 562 : remplacer « agrège… autorisé mais signalé » par un renvoi à la nouvelle sous-section. Sous *Risque global* (après l. 925) : ajouter la sous-section **« Test mixte v3.1 / v4.0 »** qui reprend la règle ci-dessus (principe, définition, bandeau, moyenne, indicateurs inchangés, exports). Ajuster les points 1 et 2 (l. 918-924) pour y renvoyer. |
| `docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md` | Sous §1.4 (après l. 52) : ajouter `**Traité** (2026-11-09) : …` qui pointe vers [[11.01 Cahier des charges]] et cite les stories vulnerabilites-6 et resultats-1 (même forme que §2.1). Le §1.4 n'a pas de ligne dans le tableau récapitulatif, donc rien à y changer. |
| `.stories/vulnerabilites-6.md` | Cocher « Règle documentée dans le CDC ». Préciser le second critère (badge « Test mixte », deux maximums, moyennes par version) et ajouter un renvoi vers la sous-section du CDC dans *Technical Notes*. Le statut reste `todo`, car la partie code reste à faire. |
| `.stories/resultats-1.md` | Critères « Maximum CVSS… » et « Score moyen pondéré… » : ajouter « (par version si test mixte, cf. vulnerabilites-6) ». Ajouter vulnerabilites-6 aux dépendances. |

Les stories s'écrivent **uniquement** via `ledger.js apply`. Il faut un seul lot JSON `{ "update": { "vulnerabilites-6.md": …, "resultats-1.md": … } }`, écrit dans le scratchpad puis supprimé :
`node C:/Users/ILP/.claude/plugins/cache/ecc/ecc/2.2.3/skills/story-lifecycle/scripts/ledger.js apply --input <json> --root .`
Le tableau de l'epic `vulnerabilites` se régénère dans le même lot. Aucun changement de statut.

## Conventions à respecter

- Rédaction en français. Espace insécable après les emojis dans les tableaux. Formules en LaTeX si besoin (ex. moyenne pondérée par version).
- Ne pas toucher aux fichiers déjà modifiés hors de ce sujet (`.obsidian/*`, `.stories/decisions-2.md`, `00.00 Index.md`, `.stories.md`) et ne pas les inclure dans le commit.
- Un commit sur `main`, en français, avec le même style que `1a15161`. Exemple : « Score global d'un test mixte CVSS v3.1 / v4.0 : CDC, 12.01 §1.4, stories vulnerabilites-6 et resultats-1. » Corps explicatif, puis la ligne Co-Authored-By.

## Vérification

1. `node …/ledger.js status --root .` : pas de `drift`, pas de `recoveryPending`. Les deux stories restent `todo`.
2. `git diff` : ne contient que les 4 fichiers ci-dessus, plus éventuellement `epics/vulnerabilites.md` s'il a été régénéré.
3. Relire la sous-section du CDC : aucune phrase n'additionne, ne moyenne ni ne compare des scores de deux versions. Les liens Obsidian `[[11.01 Cahier des charges]]` se résolvent.
4. Commit uniquement après accord de l'utilisateur.
