# Plan : traiter le §2.6 « Cycle de vie des utilisateurs » du rapport 12.01

## Contexte

Le §2.6 de [12.01](docs/10-19 Conception/12 Analyses et revues/12.01 Rapport d'analyse du CDC.md) est encore ouvert (🟡, ligne « Cycle de vie des utilisateurs (invitation, suppression) »). Il pose trois questions : l'invitation, le sort du trousseau d'un utilisateur désactivé ou supprimé, et le transfert de propriété.

Ce qui existe déjà :
- [13.03](docs/10-19 Conception/13 Décisions d'architecture/13.03 Authentification locale et coffre de clés.md) décrit l'enrôlement « à la suite d'une invitation », sans dire qui invite ni comment. Il décrit aussi la réinitialisation du coffre : les entrées sont supprimées, puis les détenteurs repartagent.
- [11.11](docs/10-19 Conception/11 Cahier des charges/11.11 Documents et chiffrement.md) chiffre un document GED **pour son seul auteur**. Si l'auteur part sans l'avoir partagé, le document devient illisible. La révocation forte y est le défaut pour « un départ d'équipe, un changement de tenant ou un incident ». Un administrateur ne peut pas contourner le chiffrement.
- [11.08](docs/10-19 Conception/11 Cahier des charges/11.08 Déroulement du test.md), *Confidentialité* : le trousseau initial d'une exécution d'outil couvre l'équipe du test. Le plan du §2.3 avait signalé, sans la trancher, la même question pour les secrets du périmètre ([11.07](docs/10-19 Conception/11 Cahier des charges/11.07 Périmètre.md)).
- La story `identite-7` (3 pts) ne donne que trois critères vagues ; « le volet trousseaux sera complété dans ged-chiffree ».

Choix validés avec l'utilisateur :
1. **Trousseau initial d'équipe** pour tout contenu chiffré d'un test (documents GED, exécutions d'outils, secrets du périmètre) : l'auteur, le chef de projet et les testeurs affectés. Le client final en est exclu.
2. **Départ** :
   - la désactivation est réversible et conserve les entrées de trousseau ;
   - à la suppression, le serveur retire aussitôt les entrées (révocation douce) ;
   - pour les tests non validés, une révocation forte est demandée aux détenteurs restants ;
   - pour les tests validés, la révocation reste douce, et cette limite est affichée.
3. **Invitation** : l'administrateur invite tout rôle. Le chef de projet invite les clients finaux des tenants où il mène un test.
4. **Forme** : un nouvel ADR **13.05**, avec une preuve openpgp.js, puis un report dans le CDC. Raison : la règle de 13.00 interdit de modifier sur le fond l'ADR accepté 13.01, dont le flux « Téléversement » chiffre pour le seul propriétaire.

Résultat attendu :
- le §2.6 est marqué ✅ ;
- 13.05 est accepté (story `decisions-8`, done) ;
- 11.06, 11.11, 11.07, 11.08, 11.02, 11.01 et 11.13 sont alignés ;
- `identite-7` est réécrite et `ged-chiffree-9` est créée.

## Étapes

> Hook GateGuard : avant le premier Bash et avant chaque création de fichier, énoncer les faits demandés. Toute écriture dans `.stories/` passe par `node C:\Users\ILP\.claude\plugins\cache\ecc\ecc\2.2.3\skills\story-lifecycle\scripts\ledger.js <allocate|apply|status> --root <racine>` (`apply --input <json>` ; un statut n'avance que d'un cran par `apply` : todo → in-progress → review → done). Les JSON d'entrée sont placés dans le scratchpad, puis supprimés. Style : phrases courtes, listes, liens `[[…]]`, espace insécable après les emojis des tableaux, pas de modification de fond d'un ADR accepté.

### 1. Preuve de faisabilité (scratchpad, hors dépôt)

`…\995eae42-…\scratchpad\poc-13.05\`, Node 26, `openpgp@6.1.1` épinglé (même version que 13.01 et 13.03). Un script `run.mjs` avec cinq utilisateurs : Alice (autrice), Paul (chef de projet), Tom (testeur), Claire (cliente finale), Nina (testeuse affectée plus tard). Attendus :
- **Trousseau d'équipe** : `encrypt({ sessionKey })` sans destinataire, puis un PKESK détaché par membre. Alice, Paul et Tom lisent ; Claire, absente du trousseau, ne lit pas.
- **Partage groupé** : Paul ajoute Nina sur N contenus (`decryptSessionKeys` puis `encryptSessionKey`). Les empreintes des blobs ne changent pas et Nina lit tout.
- **Suppression de Tom** :
  - une fois ses entrées retirées, il ne lit plus rien par le serveur ;
  - avec une ancienne entrée conservée, il lit encore **l'ancien** blob : c'est la limite à documenter ;
  - après la révocation forte faite par Paul (nouvelle CEK, nouveau blob, nouveau trousseau), Tom ne lit pas le nouveau blob, Alice et Nina le lisent, et l'empreinte du blob a changé.
- **Dernier détenteur** : une fonction pure dresse la liste des contenus qui n'ont qu'un seul détenteur actif.
- **Coût** : temps de rechiffrement en flux d'un blob de 100 Mio (meilleur de 3), pour estimer le coût d'une révocation forte par Gio.

### 2. ADR 13.05 « Trousseau d'équipe et départ d'un utilisateur »

Nouveau fichier `docs/10-19 Conception/13 Décisions d'architecture/13.05 Trousseau d'équipe et départ d'un utilisateur.md`, créé à partir de [01.01 Modèle ADR](docs/00-09 Système/01 Modèles/01.01 Modèle ADR.md). Frontmatter : `statut: accepté`, `date: 2026-11-11`, `story: decisions-8`, `remplace:` vide (13.05 complète 13.01 et ne le remplace pas).

- **Contexte** :
  - 12.01 §2.6 ;
  - risque de documents orphelins (11.11, *Téléversement*) ;
  - incohérence avec 11.08 et 11.07 ;
  - la révocation forte, défaut de 11.11, exige le navigateur d'un détenteur.
  - Contraintes : le serveur ne peut ni lire ni rechiffrer ; un départ coupe l'accès tout de suite, même sans détenteur connecté ; aucune preuve ne doit être perdue ; l'administrateur ne contourne pas le chiffrement.
- **Options** :
  1. Auteur seul (statu quo), avec partage manuel : risque de perte.
  2. Clé de séquestre du tenant : exclue, car elle contredit 11.11.
  3. Trousseau d'équipe, avec révocation forte systématique au départ : coût proportionnel au volume.
  4. Trousseau d'équipe, avec révocation douce immédiate et révocation forte pour les tests ouverts : **retenue**.
- **Décision** :
  - **Détenteurs initiaux** :
    - tout contenu chiffré d'un test (document GED, exécution d'outil, secret du périmètre) est chiffré pour l'auteur, le chef de projet et les testeurs affectés au test ; le client final en est exclu ;
    - le navigateur de l'auteur reçoit du serveur les clés publiques de l'équipe, avec leurs empreintes ;
    - un membre affecté plus tard reçoit les CEK par un **partage groupé par test**, une seule demande traitée par le navigateur d'un détenteur ;
    - les ACL restent nécessaires : il faut l'ACL ET le trousseau.
  - **Propriétaire** :
    - l'auteur à la création ; c'est un rôle côté serveur : purge anticipée, révocation forte, premier destinataire des demandes ;
    - transfert manuel, par le propriétaire ou un administrateur, vers un détenteur ; si le bénéficiaire n'est pas détenteur, le transfert attend un partage ;
    - transfert automatique à la désactivation du propriétaire, au chef de projet du test ;
    - si le chef de projet part lui-même, l'administrateur désigne un nouveau chef de projet, qui reçoit la propriété et un partage groupé.
  - **Désactivation** (réversible) :
    - connexion refusée ; sessions et jetons API révoqués ; invitations émises annulées ;
    - entrées de trousseau conservées ;
    - les tâches assignées à lui seul sont signalées au chef de projet ;
    - en cas d'incident, l'administrateur peut demander tout de suite la révocation forte.
  - **Suppression** (définitive, seulement après une désactivation) :
    - le dernier administrateur actif ne peut être ni désactivé ni supprimé ;
    - contrôle du dernier détenteur : la liste des contenus qui deviendraient illisibles est présentée ; il faut une confirmation explicite, puis ces contenus sont proposés à la purge ;
    - en une seule opération côté serveur : suppression des entrées de trousseau, des copies 1 et 2 de la clé, du hachage de `auth` et de son historique, des secrets 2FA, des ACL et des rattachements ; la clé publique est supprimée, son empreinte reste dans l'audit ;
    - pseudonymisation (« Utilisateur supprimé n° X ») : la paternité des tâches, vulnérabilités, exécutions et commentaires est conservée ; l'adresse e-mail est libérée, et une nouvelle invitation crée un nouveau compte avec de nouvelles clés.
  - **Révocation forte au départ** :
    - pour chaque test **ouvert** (Planifié, En cours, Terminé) où il détenait des entrées, une demande de rechiffrement groupée part vers le propriétaire, puis vers les autres détenteurs ;
    - elle suit la procédure de 11.11 et son avancement est visible du chef de projet et de l'administrateur ;
    - tests **Validés** : révocation douce seule.
    - Justification : la révocation forte protège contre une CEK conservée, pas contre un clair déjà copié ; elle vaut surtout pendant le test, quand les secrets sont encore exploitables.
  - **Désaffectation d'un test ou retrait d'un tenant** : les mêmes règles, limitées à ce test ou à ce tenant.
  - **Limites** :
    - les sauvegardes (14 jours) conservent les anciennes copies et entrées jusqu'à leur rotation ;
    - une demande de rechiffrement peut rester en attente si aucun détenteur ne se connecte (relances, tableau de suivi).
  - **Preuve de faisabilité** : constats et temps de l'étape 1.
- **Conséquences** :
  - CDC : 11.06, 11.11, 11.07, 11.08, 11.02 ;
  - API : invitations, désactivation et suppression atomiques et auditées, liste des clés publiques de l'équipe, demandes de partage et de rechiffrement groupées ;
  - Front-end : chiffrement multi-destinataires et traitement des demandes groupées dans le Worker crypto (13.04) ;
  - Stories : `decisions-8`, `identite-7`, `ged-chiffree-3`, `ged-chiffree-5`, `ged-chiffree-6`, `ged-chiffree-9`, `deroulement-6`, `engagement-4` ;
  - le RGPD (12.01 §5.1) reste ouvert.

Index [13.00](docs/10-19 Conception/13 Décisions d'architecture/13.00 Index des ADR.md) : ligne `13.05 | [[13.05 …]] | accepté | decisions-8`.

### 3. CDC : 11.06 Gestion des utilisateurs

[11.06](docs/10-19 Conception/11 Cahier des charges/11.06 Gestion des utilisateurs.md) reçoit une nouvelle section `## Cycle de vie des comptes`, placée entre *Isolation des données* et *Notifications* :
- **Invitation** :
  - pas d'inscription libre ;
  - l'administrateur invite tout rôle ; le chef de projet invite les clients finaux des tenants où il mène un test ;
  - l'invitation porte l'e-mail, les tenants, les rôles et, en option, les tests à affecter ;
  - lien à usage unique, valable 72 h (paramétrable), haché côté serveur ; un renvoi invalide le lien précédent ; l'invitation peut être annulée ;
  - le lien mène à l'enrôlement de 13.03 ; l'adresse invitée devient l'adresse du compte (elle entre dans `sel_auth`) ;
  - si le compte existe déjà, il est simplement rattaché au tenant, avec une notification ; s'il est désactivé, l'invitation est refusée ;
  - si l'envoi d'e-mails est désactivé, le lien s'affiche chez l'invitant, qui le transmet par un autre canal ;
  - le premier compte root est créé à l'installation par une commande `artisan` qui émet un lien d'enrôlement, sans mot de passe par défaut.
- **Désactivation**, **Réactivation**, **Suppression** : le résumé de 13.05, côté compte. Le volet trousseaux renvoie à 11.11.
- *Clé de récupération* : « À l'enrôlement » renvoie à *Invitation*.
- *Niveaux des permissions* :
  - Administrateur : « Inviter, désactiver, réactiver et supprimer des utilisateurs » ;
  - Chef de projet : « Inviter les clients finaux de ses tenants ».
- *Notifications* : ajouter l'invitation reçue, la désactivation ou la réactivation du compte, la demande de rechiffrement, le transfert de propriété, et les contenus qui n'ont plus qu'un seul détenteur (chef de projet).

### 4. CDC : 11.11 et autres parties

- [11.11](docs/10-19 Conception/11 Cahier des charges/11.11 Documents et chiffrement.md) :
  - *Téléversement d'un fichier* : l'étape 3 chiffre la CEK pour chaque membre de l'équipe du test (13.05).
  - *Gestion* : le propriétaire, son transfert, et le partage groupé à l'affectation d'un membre.
  - *Révocation d'un utilisateur* : on garde les définitions forte et douce. Le défaut au départ devient : révocation douce immédiate, puis révocation forte pour les tests ouverts. En cas d'incident, une révocation forte immédiate reste possible.
  - Nouvelle sous-section *Départ d'un utilisateur*, avec des renvois vers 11.06 et 13.05.
  - Les titres existants sont conservés (seul `#Chiffrement` est ciblé par un lien).
- [11.07](docs/10-19 Conception/11 Cahier des charges/11.07 Périmètre.md), *Objets du périmètre* : les secrets ont pour trousseau initial l'équipe du test (13.05).
- [11.08](docs/10-19 Conception/11 Cahier des charges/11.08 Déroulement du test.md), *Confidentialité* : la puce *Trousseau initial* renvoie à 13.05 ; un testeur affecté plus tard reçoit la CEK « par le partage groupé ».
- [11.02](docs/10-19 Conception/11 Cahier des charges/11.02 Socle technique.md), *Journaux d'audit* : ajouter le cycle de vie des comptes (invitation, activation, désactivation, réactivation, suppression), les transferts de propriété et les révocations au départ.
- [11.01](docs/10-19 Conception/11 Cahier des charges/11.01 Cahier des charges.md), ligne 11.06 du sommaire : ajouter « cycle de vie des comptes ».
- [11.13](docs/10-19 Conception/11 Cahier des charges/11.13 Annexes et références.md), glossaire : ajouter **Détenteur** (utilisateur dont le trousseau contient la CEK d'un contenu) et **Propriétaire** (détenteur qui gère le partage, la révocation et la purge d'un contenu), après *Clé de récupération*.
- [14.01](docs/10-19 Conception/14 Modèle de données/14.01 Schéma des objets métier.md), *Hors schéma › Objets transverses* : ajouter « invitations, demandes de partage et de rechiffrement ».

### 5. 12.01 et compteurs

- §2.6 : paragraphe `**Traité** (2026-11-11) : …` au format des autres. Il cite 13.05 et 11.06 et résume :
  - l'invitation (qui, lien de 72 h, root à l'installation) ;
  - la désactivation, réversible, qui conserve les entrées ;
  - la suppression : pseudonymisation, révocation douce immédiate, révocation forte pour les tests non validés ;
  - le trousseau d'équipe, qui évite les documents orphelins, et le transfert de propriété au chef de projet ;
  - les stories `decisions-8`, `identite-7` et `ged-chiffree-9`.
- Synthèse : `🔲 Ouvert` devient `✅ [[13.05 Trousseau d'équipe et départ d'un utilisateur]]`, avec une espace insécable après l'emoji.
- CLAUDE.md et README.md : 67 stories deviennent 69.

### 6. Stories (un `allocate` par epic, puis les `apply`)

`allocate --epic decisions --count 1` (decisions-8 attendue) et `allocate --epic ged-chiffree --count 1` (ged-chiffree-9 attendue). Utiliser les ID renvoyés.

| **Story** | **Modification** |
| --- | --- |
| decisions-8 (nouvelle) | « ADR : trousseau d'équipe et départ d'un utilisateur », 2 pts, Sprint 1 (comme decisions-7). Critères : détenteurs initiaux ; propriété et transfert ; désactivation et suppression ; révocation au départ ; preuve consignée. Créée en `todo`, puis trois `apply` : in-progress, review, done (critères cochés) |
| identite-7 | Points 3 → 5. Critères : invitation (rôles autorisés, lien de 72 h à usage unique, renvoi et annulation, compte existant rattaché, lien affiché sans e-mail) ; root créé par `artisan` ; désactivation et réactivation (sessions et jetons révoqués) ; suppression en deux temps, dernier administrateur protégé, pseudonymisation, données d'authentification et copies de clé supprimées ; audit et notifications. Dépend de identite-5, identite-6 et ged-chiffree-1 ; volet trousseaux : ged-chiffree-9 |
| ged-chiffree-9 (nouvelle) | « Départ d'un utilisateur : trousseaux et propriété », 5 pts, `todo`. Critères : entrées retirées à la suppression ; demandes de rechiffrement groupées pour les tests ouverts, avec leur avancement ; transfert de propriété, manuel et automatique ; contrôle du dernier détenteur ; désaffectation d'un test ou d'un tenant ; preuve multi-utilisateurs en test. Dépend de ged-chiffree-6 et identite-7 |
| ged-chiffree-3 | Critère « trousseau initial » → « trousseau initial : équipe du test, client final exclu (13.05) » |
| ged-chiffree-5 | Critère ajouté : partage groupé par test à l'affectation d'un membre |
| ged-chiffree-6 | Note : défaut au départ fixé par 13.05, traité dans ged-chiffree-9 |
| deroulement-6 | Critère du trousseau initial et note : renvoi à 13.05, « partage groupé » |
| engagement-4 | Note : secrets chiffrés pour l'équipe du test (13.05) |
| epics/identite | *Scope* : « Cycle de vie utilisateur (invitation, désactivation, suppression) » |
| epics/ged-chiffree | *Scope* : « Trousseau d'équipe, propriété et départ d'un utilisateur » |
| epics/decisions | *Scope* : ajouter « trousseau d'équipe et départ d'un utilisateur » |

### 7. Commit et export de session

- Commit en français sur `main` : « Cycle de vie des utilisateurs : ADR 13.05, CDC, 12.01 §2.6, stories decisions-8, identite-7, ged-chiffree-9. » Le corps résume les choix ; ligne Co-Authored-By.
- Fichiers à indexer : 13.05, 13.00, 11.01, 11.02, 11.06, 11.07, 11.08, 11.11, 11.13, 14.01, 12.01, CLAUDE.md, README.md et `.stories/` (stories, epics, sprint).
- **Exclus** : `.obsidian/*`, `12.03 Revue Cursor.md` et `21.01` (modifications hors tâche, présentes avant la session).
- Export de session : `/ecc:save-session`, copie dans `.claude/session-data/`, commit séparé.

## Vérification

- **Preuve** : les constats et les temps reportés dans 13.05 correspondent à la sortie de `run.mjs`.
- **Ledger** : `ledger.js status` signale `drift` vide, `recoveryPending: false` et 69 stories ; `decisions-8` est `done`.
- **Liens** : un script Node dans le scratchpad contrôle que chaque `[[…]]` de `docs/` et `.stories/` (ancres comprises) pointe vers une cible existante.
- **Cohérence** : `grep` « propriétaire », « trousseau initial » et « révocation forte » dans `docs/` ; plus aucune mention ne dit « auteur seul » ou « pour lui-même » au téléversement.
- **Espace insécable** : `grep` sur U+00A0 après ✅ dans la ligne modifiée de la synthèse de 12.01.
- **`git diff --stat` avant le commit** : seuls les fichiers de l'étape 7 apparaissent.

## Risques

| **Risque** | **Mitigation** |
| --- | --- |
| Trousseau d'équipe : plus de détenteurs, donc une surface plus large | Toujours ACL ET trousseau ; client final et administrateurs exclus ; limite assumée dans 13.05 |
| Demandes de rechiffrement jamais traitées | La révocation douce a déjà coupé l'accès ; relances et tableau de suivi pour le chef de projet et l'administrateur |
| Anciennes copies présentes dans les sauvegardes | Limite documentée (rotation de 14 jours) ; RGPD §5.1 ouvert |
| Contenus antérieurs ou tests à un seul membre, sans second détenteur | Contrôle du dernier détenteur avant suppression ; liste « détenteur unique » signalée au chef de projet |
| La configuration e-mail (§3.4, decisions-4) n'est pas tranchée | L'invitation prévoit un lien affiché sans e-mail ; le reste relève de decisions-4 |
