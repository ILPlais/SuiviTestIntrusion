# Plan : accepter toutes les icônes Nerd Fonts, même celles sans licence

Plan établi le 2026-11-23 avec `/ecc:plan`, validé par l'utilisateur (« Oui, option A. »), dans la story [[socle-8]].

## Décision

Tout jeu de glyphes de Nerd Fonts devient utilisable, quelle que soit sa licence : MIT, Apache 2.0 (Material Design, Pictogrammers Free License), SIL OFL 1.1 (Font Awesome en police, Pomicons, Weather Icons), CC BY 4.0 (Codicons) et Font Logos, publié sans licence ; le risque juridique de ce dernier est assumé par l'utilisateur.

Option retenue : **A, sous-ensemble** (quelques Ko) plutôt que B, police complète (1,2 Mo) : tout glyphe est autorisé, ajouté à `src/icons/glyphs.json` puis police régénérée par `scripts/subset-nerd-font.mjs`.

## Étapes

1. [[13.07 Licence, langues et accessibilité]] : nouvelle décision sur la police d'icônes.
2. `frontend/src/assets/fonts/LICENSE.md` : tableau des jeux de glyphes et textes complets des licences (sources de Nerd Fonts 3.5.1) ; correction : Material Design suit la Pictogrammers Free License (polices sous Apache 2.0), Font Awesome place ses glyphes en police sous OFL 1.1.
3. `glyphs.json` : plus de restriction aux glyphes `nf-md-*`.
4. `scripts/subset-nerd-font.mjs` : échec si un code demandé manque dans la police source.
5. Test unitaire : zones d'usage privé de Nerd Fonts (U+E000–U+F8FF, U+F0001–U+F1AF0), sans doublon.
6. Licences jointes au build (`assets/LICENSE-police-icones.md`, plugin de `vite.config.ts`), pour l'image Docker.
7. Story socle-8 et [[43.01 Aperçu de l'interface]] mises à jour ; CI verte.

Ajout demandé en cours de route : champs de même largeur sur l'écran de connexion (largeur fixe de 60 caractères, contrôle e2e).

## Risques

- Élevé, accepté : Font Logos, sans licence (logos de marques) ; aucun glyphe de ce jeu n'est employé à ce jour.
- Moyen : obligations de l'OFL et de la CC BY 4.0, couvertes par la licence jointe au build.
- Faible : le contrôle de licences de la CI ne lit que les paquets ; la décision écrite dans 13.07 couvre la police.
