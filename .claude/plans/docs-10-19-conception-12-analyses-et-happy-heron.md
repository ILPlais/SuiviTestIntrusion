# Plan : traiter le §2.3 « Structure d'une exécution d'outil » du rapport 12.01

## Contexte

Le §2.3 de [12.01](docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md) est encore ouvert (🟡 dans la synthèse, ligne « Import de sorties d'outils (Nmap, Burp…) »). Le CDC ([11.08, *Outils*](docs/10-19 Conception/11 Cahier des charges/11.08 Déroulement du test.md)) ne compte que trois lignes : des exemples d'outils, « avec les commandes exécutées et les réponses », et PrismJS.

Ce qui existe déjà :
- [14.01](docs/10-19 Conception/14 Modèle de données/14.01 Schéma des objets métier.md) contient `Outil ||--o{ Exécution d'outil` et `Tâche }o--o{ Exécution d'outil`. Le lien outil ↔ vulnérabilité « dépend de la structure d'une exécution (12.01 §2.3) ». C'est le premier point de la liste *À préciser*.
- 11.08 *Tâches* relie déjà la tâche aux « exécutions d'outils ».
- La story `deroulement-6` exige « outil, version, commande, sortie, phase, tâche, liens vulnérabilités » et un « Import Nmap XML ». L'epic `deroulement`, elle, met l'import automatique hors périmètre : ce point est incohérent.
- 13.04 traite déjà les sorties d'outils comme du contenu hostile (PrismJS via `sti-render`).

Choix validés avec l'utilisateur :
1. **Tout chiffré** : la commande et la sortie sont chiffrées dans le navigateur (OpenPGP, 13.01, un trousseau par exécution). Seules les métadonnées restent en clair.
2. **Import natif** : Nmap XML en V1, dans une story dédiée. `.nessus`, Burp Suite et OWASP ZAP passent en V2, dans une story conservée.
3. **Granularité** : une exécution = une commande (facultative) et sa sortie. Une session interactive est une seule exécution.
4. **Ajout de l'utilisateur** : gérer aussi les enregistrements **asciinema** (fichiers asciicast `.cast`). C'est le cas type de la session interactive : import en V1, rejoué par le lecteur asciinema, avec une transcription texte.

Résultat attendu : §2.3 marqué ✅ dans 12.01. Le CDC, 14.01, 13.04 et les stories sont alignés. Pas d'ADR nouveau, car le chiffrement applique 13.01 tel quel. Le lecteur asciinema fait l'objet d'un complément de 13.04, appuyé sur une preuve.

## Étapes

> Hook GateGuard : avant le premier Bash et avant chaque création de fichier, énoncer les faits demandés. Toute écriture dans `.stories/` passe par `node C:\Users\ILP\.claude\plugins\cache\ecc\ecc\2.2.3\skills\story-lifecycle\scripts\ledger.js <cmd> --root <racine>`. Les JSON d'entrée sont placés dans le scratchpad, puis supprimés. Style : phrases courtes, listes, liens `[[…]]`, espace insécable après les emojis des tableaux.

### 1. Preuve : lecteur asciinema sous la CSP de 13.04 (scratchpad, hors dépôt)

- Copier la mini SPA de 13.04 `…\f0b4c3fd-…\scratchpad\poc-csp-13.04\` et son serveur `poc-server\` dans le scratchpad de cette session (`poc-asciinema\`, `poc-asciinema-server\`). Les dépendances et la CSP restent inchangées.
- Format : faire lire par l'agent `web-fetch` la spécification asciicast v2 et v3 (docs.asciinema.org), pour confirmer les champs de l'en-tête (`command`, `timestamp`, `title`, `term`/`width`) et les codes d'événement (`o`, `i`, `m`, `r`, `x`). Vérifier aussi qu'asciinema-player lit la v3.
- Ajouter `asciinema-player` dans une version épinglée, publiée depuis au moins deux semaines (`npm view asciinema-player time`). Le lecteur reçoit le contenu en mémoire (`{ data }`), jamais une URL.
- Fixtures `.cast` v2 et v3, chacune contenant :
  - une charge `<img src=x onerror=…>` et `<script>` dans la sortie ;
  - un hyperlien OSC 8 vers `javascript:` ;
  - un OSC 52 (presse-papiers) ;
  - un marqueur et un événement d'entrée `i`.
- Lancer `run.mjs` (puppeteer-core) sous Firefox 159 et Chrome for Testing 157. Attendus :
  - lecture, pause et marqueurs fonctionnent ;
  - aucune violation CSP ni Trusted Types inattendue ;
  - charges affichées comme du texte, aucun lien `javascript:`, aucune requête réseau ;
  - transcription extraite (événements `o`, séquences de contrôle retirées) identique au texte attendu.
- Points à surveiller :
  - WASM du lecteur sous `'wasm-unsafe-eval'` ;
  - WASM ou ressources inlinées en `data:` (bloquées par `connect-src` et `assetsInlineLimit: 0`) ;
  - gabarits HTML du lecteur qui passeraient par la politique `default`.
- **Repli si la preuve échoue** : en V1, la transcription texte seule. Le rejeu passe en V2 (ou dans une iframe `sandbox`, comme l'évolution prévue pour Mermaid). Le CDC et la story sont rédigés en conséquence.

### 2. CDC : réécrire 11.08 *Outils*

Fichier : [11.08 Déroulement du test](docs/10-19 Conception/11 Cahier des charges/11.08 Déroulement du test.md). On garde le titre `## Outils`, sur lequel pointent les liens. La section reçoit les sous-sections suivantes.

- **Catalogue d'outils**
  - Commun au tenant. Pré-rempli avec Nmap, Metasploit, Burp Suite, sqlmap, Impacket, Hashcat, PowerShell, Terminal, etc., et complété par les testeurs.
  - Une entrée porte : nom, lien, langages PrismJS par défaut de la commande et de la sortie (ou texte brut).
  - La version se saisit à chaque exécution. Elle est pré-remplie par la dernière version saisie pour cet outil dans le test.
- **Exécution d'outil** : une commande (facultative) et sa sortie. Une session interactive (shell, Meterpreter, enregistrement asciinema) est une seule exécution dont la sortie est la transcription. Champs :
  - référence, unique dans le test ;
  - outil et version ;
  - auteur ;
  - dates et heures de début et de fin ;
  - phase : obligatoire, pré-remplie par celle de la tâche d'origine ;
  - commande et sortie, chacune avec son langage ;
  - origine : saisie, ou import (format et nom du fichier) ;
  - liens n-n vers les tâches, les vulnérabilités (« révèle ») et les objets du périmètre visés (cibles).
- **Confidentialité**
  - La commande et la sortie (ou le fichier importé) forment un contenu unique. Il est chiffré dans le navigateur ([[13.01]]) et stocké hors base, comme les blobs de la GED.
  - Trousseau initial : l'auteur, le chef de projet et les testeurs affectés au test. Le client final n'y figure pas. Un testeur affecté plus tard reçoit la CEK par le flux de partage de [[11.11]].
  - Rétention, purge et révocation suivent les règles de la GED.
  - En clair : uniquement les métadonnées. La recherche porte sur elles.
  - L'historique trace les métadonnées, jamais le contenu.
- **Affichage**
  - On garde le paragraphe PrismJS (langages minimaux). Le rendu se fait par `Prism.highlight` sur le texte brut, inséré via le module de rendu ([[13.04]]).
  - Au-delà d'une taille paramétrable (défaut : 1 Mio), la sortie s'affiche tronquée et le texte complet reste téléchargeable.
- **Import de sorties natives**
  - Règles communes :
    - le fichier est lu et analysé dans le navigateur ; il devient le contenu chiffré de l'exécution et reste téléchargeable ;
    - il est traité comme hostile : taille maximale paramétrable, analyse sans résolution d'entités externes, valeurs extraites traitées comme du texte ;
    - le contenu d'une exécution importée n'est pas modifiable ; ses métadonnées et ses liens le restent.
  - **Nmap XML (V1)** :
    - métadonnées : version (`nmaprun@version`), commande (`@args`), début (`@start`) et fin (`runstats/finished@time`) ;
    - chaque hôte actif est rapproché des machines du périmètre par son adresse IP, et ses ports ouverts sont proposés comme services ; le testeur valide ;
    - les machines rapprochées deviennent les cibles de l'exécution ;
    - un hôte qui correspond à une machine **exclue** est signalé au chef de projet (balayage hors périmètre) ;
    - un hôte inconnu est listé ; son ajout au périmètre revient au chef de projet.
  - **asciinema (V1)** :
    - formats asciicast v2 et v3 (`.cast`) ;
    - métadonnées : commande (`command`), début (`timestamp`), fin (début + durée) ;
    - outil : choisi par le testeur, pré-rempli par « Terminal » ;
    - le lecteur rejoue l'enregistrement (pause, vitesse, marqueurs) ;
    - la transcription texte (sortie sans séquences de contrôle) s'affiche et alimente les annexes de preuves. Elle est fidèle pour une ligne de commande, approximative pour un programme plein écran ;
    - les événements d'entrée (`i`) peuvent contenir des mots de passe tapés sans écho : ils restent dans le fichier chiffré et sont exclus de la transcription.
  - **V2** : `.nessus`, Burp Suite XML et OWASP ZAP. Ils produisent des vulnérabilités brouillon (titre, CVE/CWE, description, composants concernés), dédoublonnées et à valider.

### 3. CDC : autres parties
- [11.09](docs/10-19 Conception/11 Cahier des charges/11.09 Vulnérabilités et remédiation.md), *Fiche de vulnérabilité* : ajouter « Exécutions d'outils liées (commandes et sorties qui établissent le constat) ».
- [11.04](docs/10-19 Conception/11 Cahier des charges/11.04 Exports et modèles de rapport.md) :
  - modèle Technique : « outils » devient « exécutions d'outils (outil, version, dates, cibles et liens ; le contenu chiffré va dans les annexes de preuves) » ;
  - modèle Annexes preuves : commandes, sorties et transcriptions asciinema, déchiffrées dans le navigateur de l'exportateur qui détient les CEK ;
  - migration : ajouter « catalogue d'outils, exécutions d'outils (contenu chiffré, avec les blobs) ».
- [11.11](docs/10-19 Conception/11 Cahier des charges/11.11 Documents et chiffrement.md), puce *Aperçus* : les enregistrements asciinema se rejouent dans le lecteur intégré (13.04).
- [11.01](docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md), ligne 11.08 du sommaire : « outils » devient « outils, exécutions et imports ».

### 4. Complément de 13.04
Dans [13.04](docs/10-19 Conception/13 Décisions d'architecture/13.04 Protection de la clé privée contre le XSS.md), sans changer le statut :
- *Rendu des contenus hostiles* : une puce asciinema-player (contenu déchiffré passé en mémoire, jamais une URL ; insertion selon le constat de la preuve).
- *Aperçus* : rejeu asciinema autorisé.
- *Preuve de faisabilité* : un paragraphe « Complément du 2026-11-11 » (version, navigateurs, résultats).
- *Conséquences › Stories* : ajouter `deroulement-8`.
- Si la preuve impose un ajustement (politique Trusted Types, directive), mettre à jour le tableau CSP et la justification.

### 5. 14.01 Schéma des objets métier
- Version du 2026-11-11.
- Liens ajoutés au diagramme :
  - `"Phase" ||--o{ "Exécution d'outil" : "voit lancer"` ;
  - `"Exécution d'outil" }o--o{ "Objet du périmètre" : "cible"` ;
  - `"Exécution d'outil" }o--o{ "Vulnérabilité" : "révèle"`.
- Paragraphe des cardinalités : « une exécution d'outil appartient à une seule phase ».
- Ligne « Outil ↔ vulnérabilité » du tableau : « Direct par l'exécution d'outil, plusieurs à plusieurs ([[11.08 Déroulement du test#Outils|11.08, Outils]]) ».
- *À préciser* : retirer le point sur l'exécution d'outil.

### 6. 12.01 et compteurs
- §2.3 : paragraphe `**Traité** (2026-11-11) : …` au format des autres points. Il contient :
  - le catalogue et la structure de l'exécution ;
  - le chiffrement intégral et les métadonnées en clair ;
  - l'import dans le navigateur : Nmap et asciinema en V1, Nessus, Burp et ZAP en V2 ;
  - le lien outil ↔ vulnérabilité, devenu direct dans 14.01 ;
  - les stories deroulement-6 à deroulement-9.
- Synthèse : `🔲 Ouvert` devient `✅ [[11.08 Déroulement du test]] (Nessus, Burp, ZAP en V2)`, avec une espace insécable après l'emoji.
- CLAUDE.md et README.md : 63 stories deviennent 66.

### 7. Stories (un `allocate`, puis un seul lot `apply`)
`ledger.js allocate --epic deroulement --count 3`. IDs attendus : deroulement-7 à 9 ; utiliser ceux que renvoie la commande.

| **Story** | **Modification** |
| --- | --- |
| deroulement-6 | Titre « Catalogue d'outils et exécutions ». Points 3 → 5. Critères : catalogue ; champs de l'exécution ; contenu chiffré et trousseau initial (équipe du test) ; liens n-n vers tâches, vulnérabilités et cibles ; PrismJS et affichage tronqué ; historique sans contenu. On retire « Import Nmap XML ». Dépend de deroulement-1 et ged-chiffree-3 |
| deroulement-7 (nouvelle) | « Import Nmap XML », 5 pts, `todo`, non assignée. Critères : analyse dans le navigateur ; métadonnées ; rapprochement IP ; services proposés ; alerte hors périmètre ; contenu non modifiable ; tests sur fichiers réels et hostiles (charges XSS, entités, fichier tronqué). Dépend de deroulement-6 et engagement-4 |
| deroulement-8 (nouvelle) | « Import d'enregistrements asciinema », 3 pts. Critères : asciicast v2 et v3 ; lecteur sous la CSP de 13.04 ; transcription sans séquences de contrôle ; événements `i` exclus ; fixtures hostiles. Dépend de deroulement-6 |
| deroulement-9 (nouvelle) | « Import Nessus, Burp Suite et OWASP ZAP », 8 pts, V2. Critères : vulnérabilités brouillon à valider, dédoublonnage, rapprochement des composants. Note « Reportée en V2 ». Dépend de deroulement-7 et vulnerabilites-3 |
| epics/deroulement | *Scope* : « Catalogue d'outils et exécutions chiffrées (PrismJS) », « Import Nmap XML et asciinema ». *Out of Scope* : « Import Nessus, Burp Suite, OWASP ZAP (V2, story deroulement-9 conservée) » |
| vulnerabilites-3 | « et outils » devient « et exécutions d'outils (deroulement-6) » |
| exports-1 | Note : le modèle Technique liste les exécutions sans leur contenu |
| exports-4 | Critère : commandes, sorties et transcriptions asciinema déchiffrées et jointes côté client |
| exports-5 | Note : catalogue d'outils et exécutions, contenu chiffré dans le paquet des blobs |

### 8. Commit et export de session
- Commit en français sur `main` : « Structure d'une exécution d'outil : CDC, 13.04, 14.01, 12.01 §2.3, stories deroulement-6 à 9. » Le corps résume les choix ; ligne Co-Authored-By.
- Fichiers à indexer : 11.01, 11.04, 11.08, 11.09, 11.11, 13.04, 14.01, 12.01, CLAUDE.md, README.md et `.stories/` (stories et epic).
- **Exclus** : `.obsidian/*`, `12.03 Revue Cursor.md` et `21.01` (modifications hors tâche, fins de ligne).
- Export de session : `/ecc:save-session`, copie dans `.claude/session-data/`, commit séparé.

## Vérification
- **Preuve** : les constats reportés dans 13.04 correspondent aux sorties de `run.mjs` dans les deux navigateurs.
- **Ledger** : `ledger.js status` signale `drift` vide, `recoveryPending: false` et 66 stories.
- **Liens** : un script Node dans le scratchpad contrôle que chaque `[[…]]` de `docs/` et `.stories/` (ancres comprises) pointe vers une cible existante.
- **Mermaid** : le `erDiagram` de 14.01 passe `mermaid.parse` dans la mini SPA.
- **Espace insécable** : `grep` sur U+00A0 après ✅ dans la ligne modifiée de la synthèse de 12.01.
- **`git diff --stat` avant le commit** : seuls les fichiers de l'étape 8 apparaissent.

## Risques
| **Risque** | **Mitigation** |
| --- | --- |
| asciinema-player incompatible avec Trusted Types ou `assetsInlineLimit: 0` | Repli prévu à l'étape 1 : transcription seule en V1, rejeu en V2 ou iframe `sandbox` |
| Trousseau initial « équipe du test » : règle nouvelle, qui vaut aussi pour les secrets du périmètre (non tranchée) | Énoncée pour les exécutions seulement ; signaler le cas des secrets à l'utilisateur, sans le traiter ici |
| Rapport Technique généré côté serveur, sans les commandes | Assumé : le contenu va dans les annexes de preuves, construites dans le navigateur (11.04) |
