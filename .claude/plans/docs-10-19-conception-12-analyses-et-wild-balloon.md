# Plan : clore 12.01 §1.5 (table « Portée (S) » sans colonne « Valeur »)

## Contexte

Le §1.5 du rapport 12.01 relève que la table *Portée (S)* du CDC n'a pas de colonne « Valeur ». Ce choix est correct selon la spécification CVSS v3.1, mais il doit être noté explicitement pour éviter la confusion.

État actuel :
- Le commit HEAD contenait une colonne « Valeur » remplie de « _Pas de valeur propre_ : … ». Dans la copie de travail (non commitée), cette colonne a été retirée du CDC. La table a donc de nouveau deux colonnes, comme le décrit le §1.5.
- La note qui suit la table ([11.01 Cahier des charges.md:670](docs/10-19%20Conception/11%20Cahier%20des%20charges/11.01%20Cahier%20des%20charges.md#L670)) dit déjà explicitement que la portée n'a pas de coefficient numérique (CVSS v3.1 §7.4). Elle explique aussi que la portée sélectionne PR, la formule d'Impact et le calcul du ScoreBase. La demande du §1.5 est donc satisfaite par le CDC.
- Aucune story ne cite le §1.5 ni la portée : il n'y a pas de story à créer ni à modifier.

## Modifications

1. **[12.01 Rapport d'analyse du CDC.md](docs/10-19%20Conception/12%20Analyses%20et%20revues/12.01%20Rapport%20d'analyse%20du%20CDC.md)**, §1.5 : ajouter après le paragraphe une ligne au format des autres points traités :

   > **Traité** (2026-11-09) : la table *Portée (S)* du [[11.01 Cahier des charges]] reste sans colonne « Valeur ». Une note sous la table précise, selon la spécification CVSS v3.1 (§7.4), que la portée n'a pas de coefficient numérique et qu'elle sélectionne les valeurs de PrivilègesRequis, la formule d'Impact et le calcul du ScoreBase.

2. **CDC, table *Privilèges requis (PR)*** ([ligne 650](docs/10-19%20Conception/11%20Cahier%20des%20charges/11.01%20Cahier%20des%20charges.md#L650)) : supprimer la ligne vide en fin de table. C'est un artefact de l'éditeur de tableaux d'Obsidian, présent dans la même modification non commitée, qui s'affiche comme une rangée vide.

3. **Commit** (en français, sur `main`) des seuls fichiers CDC et 12.01. Les modifications `.obsidian/*` et `.stories.md` (non suivi) sont laissées hors du commit.
   Message : `Table Portée (S) sans colonne Valeur : CDC, 12.01 §1.5.`

## Vérification

- `git diff` : seule la colonne « Valeur » disparaît de la table Portée et seule la ligne vide disparaît de la table PR ; la ligne « Traité » est ajoutée au §1.5.
- Vérifier dans l'aperçu Obsidian que les tables PR et Portée s'affichent correctement (formules LaTeX comprises).
- `git status` après le commit : il ne reste que `.obsidian/*` et `.stories.md`.
