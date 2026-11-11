# Plan : decisions-3 — ADR 13.03, authentification locale et coffre de clés

## Contexte

La story decisions-3 devait concilier LDAP/AD avec une clé privée protégée par le mot de passe (12.01 §1.2). **Décision de l'utilisateur : pas de LDAP ni d'Active Directory en V1.** Le conflit d'origine disparaît, mais il en reste un : avec une connexion locale classique, le mot de passe part au serveur à chaque connexion. S'il protège aussi la clé privée, un serveur compromis peut la déchiffrer, ce qui contredit le principe « le serveur ne voit jamais le clair ». Le diagramme de connexion du CDC dit d'ailleurs déjà que le mot de passe « n'a jamais quitté le client ».

Choix faits avec l'utilisateur :

- **Mot de passe unique, dérivé côté client** : le navigateur calcule une *valeur d'authentification* et c'est elle, pas le mot de passe, que reçoit le serveur. Le même mot de passe protège la clé privée (S2K de 13.02).
- **Clé de récupération** générée à l'enrôlement. Pas de séquestre administrateur.
- **identite-2 (LDAP/AD)** reste dans le backlog, marquée « V2 » ; l'epic identite passe LDAP/AD en hors périmètre (V2).

Résultat attendu : ADR 13.03 accepté, CDC et 12.01 alignés (§1.2, §4.1, §4.2), stories mises à jour par ledger.js, decisions-3 en `done`.

## Contenu de l'ADR (13.03 Authentification locale et coffre de clés.md)

**Options** : passphrase de coffre distincte / mot de passe unique dérivé côté client / mot de passe unique envoyé au serveur ; LDAP/AD en V1 ou reporté. Retenu : dérivé côté client, LDAP/AD en V2.

**Valeur d'authentification.** C'est la conception qui laisse **13.02 inchangé**.

- `auth = Argon2id(mot de passe, sel_auth)` dans le navigateur, avec les mêmes paramètres que le S2K de 13.02 (t=2, p=4, 128 Mio) et une sortie de 32 octets. Bibliothèque `argon2id` 1.0.1 (openpgpjs, MIT), celle qu'openpgp.js embarque déjà.
- `sel_auth` = 16 premiers octets de `SHA-256("sti-auth-v1" ‖ 0x00 ‖ id_instance ‖ 0x00 ‖ e-mail normalisé)`. Il est déterministe, donc rien à demander au serveur avant la connexion et aucune énumération de comptes possible. `id_instance` est un UUID public tiré à l'installation.
- Le serveur reçoit `auth` à la place du mot de passe et le hache avec le pilote Laravel `argon2id` (13.02). Il ne voit jamais le mot de passe.
- La clé privée reste protégée par le S2K Argon2id d'openpgp.js sur le mot de passe (13.02), avec ses propres sels. Connaître `auth` ne permet de la déchiffrer qu'en cassant le mot de passe par force brute, au même coût Argon2id.
- La clé privée chiffrée n'est livrée qu'après une authentification complète (2FA comprise si elle est active).
- Coût à la connexion : une dérivation pour `auth` (environ 0,4 s) plus le déverrouillage (environ 0,8 s).

**Parcours, décrits en séquences Mermaid :**

1. **Enrôlement** (invitation identite-7). L'utilisateur choisit son mot de passe, contrôlé par la politique côté client. Le navigateur :
   - calcule `auth` ;
   - génère la paire v6 ;
   - chiffre la clé privée avec le mot de passe ;
   - génère une **clé de récupération** de 128 bits, affichée en Base32 Crockford par groupes, et chiffre avec elle une seconde copie de la clé privée (même S2K) ;
   - affiche cette clé une seule fois et la propose au téléchargement en `.txt`, puis demande de la ressaisir avant d'activer le compte ;
   - envoie au serveur `auth`, la clé publique, la clé privée chiffrée et la copie de récupération.
2. **Connexion** : comme au point « Valeur d'authentification ».
3. **Changement de mot de passe** (connecté) :
   - l'ancien `auth` est vérifié ;
   - rotation S2K selon 13.02 et nouvel `auth` ;
   - remplacement atomique (verrou optimiste) ;
   - les autres sessions sont révoquées ; audit et e-mail à l'utilisateur.
4. **Mot de passe oublié avec la clé de récupération** :
   - lien de réinitialisation par e-mail (Laravel), 2FA si elle est active ;
   - le serveur renvoie la copie de récupération ;
   - le navigateur la déchiffre avec la clé de récupération, demande un nouveau mot de passe, rechiffre la clé et calcule le nouvel `auth` ;
   - il génère une **nouvelle** clé de récupération, puisque l'ancienne a été saisie ;
   - remplacement atomique, sessions révoquées, audit et e-mail.
5. **Perte des deux secrets : réinitialisation du coffre.**
   - Elle passe par le même lien e-mail, mais exige la **validation d'un administrateur**.
   - Le navigateur génère une nouvelle paire et une nouvelle clé de récupération.
   - Les anciennes entrées de trousseau sont supprimées, car devenues illisibles. Les détenteurs encore autorisés sont notifiés et doivent repartager. Audit.
   - L'administrateur ne peut jamais lire les fichiers.
6. **Changement d'e-mail** : `sel_auth` change. Le mot de passe est redemandé pour recalculer `auth`, et le remplacement est atomique.

**Politique de mot de passe (§4.1).** Valeurs réglables à l'installation.

- Au moins **15 caractères** : le mot de passe protège la clé contre une attaque hors ligne, que la 2FA n'empêche pas. Au plus 128.
- Pas de règle de composition. Liste de refus embarquée et estimation de robustesse dans le navigateur.
- Pas d'expiration périodique ; changement imposé en cas de suspicion de compromission.
- Pas de réutilisation des **5 derniers** mots de passe. C'est vérifiable côté serveur, parce que `auth` est déterministe : on compare avec l'historique des hachages.
- Limite assumée : le serveur ne voit pas le mot de passe, donc longueur et robustesse ne sont contrôlées que dans le navigateur.

**Verrouillage (§4.2).** Compteur d'échecs par compte, en plus de la limite Auth par IP.

- Délai croissant à partir de 5 échecs consécutifs, verrouillage de 15 minutes à 10 échecs.
- E-mail à l'utilisateur, audit, déverrouillage possible par un administrateur, remise à zéro après une connexion réussie.
- Limite : le verrouillage ne protège pas la clé chiffrée contre une attaque hors ligne. Seul le coût Argon2id le fait.

**Conséquences** :

- quand LDAP/AD arrivera en V2, le mot de passe de l'annuaire partira au serveur : il faudra une passphrase de coffre et un nouvel ADR ;
- le front-end dépend de `argon2id` (déjà présent par openpgp.js) ;
- l'API de connexion reçoit `auth` (Base64), jamais le mot de passe ;
- la réinitialisation par e-mail ne donne accès qu'à la copie de récupération chiffrée.

## Tâches

1. **Preuve de faisabilité d'abord** : `scratchpad/poc/poc-auth.mjs`, sur le modèle de `poc-kdf.mjs` (même `check()`, chargement par `createRequire(process.argv[2])`, journaux en français), avec `npm install argon2id@1.0.1` dans `scratchpad/deps/`. Elle vérifie :
   - que `auth` est déterministe (même mot de passe, même e-mail, même instance) et change si l'e-mail ou l'instance change ;
   - que `argon2id` (WASM) et `crypto.argon2Sync` de Node donnent la même sortie, ce qui valide la bibliothèque ;
   - l'enrôlement : la copie mot de passe et la copie récupération ont la même empreinte ;
   - la récupération : nouveau mot de passe, nouvel `auth`, un PKESK antérieur toujours lisible, l'ancien mot de passe refusé, l'ancienne copie de récupération remplacée ;
   - la réinitialisation du coffre : nouvelle empreinte et ancien PKESK illisible ;
   - la détection de réutilisation : un `auth` identique est reconnu par `argon2Verify` dans l'historique ;
   - les temps : une dérivation `auth` sous Node, puis une connexion complète (`auth` + déverrouillage) dans Firefox headless, avec le banc `bench.html` / `serve.mjs` étendu.
2. **Vérifier les références** citées : NIST SP 800-63B-4 (15 caractères pour un facteur unique, pas de composition ni d'expiration), RFC 9106 et Laravel. La recherche web passe par l'agent web-fetch si nécessaire. On n'écrit que ce qui est confirmé.
3. **Rédiger** `docs/10-19 Conception/13 Décisions d'architecture/13.03 Authentification locale et coffre de clés.md` à partir du modèle `01.01`, sur le modèle de 13.02 (en-tête, tableaux en gras, preuve datée, espaces normales comme 13.01 et 13.02).
4. **Index 13.00** : ligne `13.03 | accepté | decisions-3`.
5. **CDC 11.01**, en gardant les espaces insécables avant « ; » et « : » propres à ce fichier :
   - § Techniques d'authentification (~l. 314) : seul le mode local en V1, LDAP et AD « en V2 », un paragraphe sur la valeur d'authentification ;
   - nouvelles sous-sections **Politique de mots de passe**, **Verrouillage de compte** et **Clé de récupération** (enrôlement et oubli), avec renvoi à 13.03 ;
   - diagramme de connexion (~l. 760) : `auth` envoyé au serveur, clé chiffrée livrée après authentification ;
   - Points de vigilance, Rotation du mot de passe : ajouter le nouvel `auth` ;
   - glossaire : **valeur d'authentification**, **clé de récupération**.
6. **12.01** : « **Traité** (2026-11-02) » sous §1.2 (LDAP/AD reportés en V2, dérivation côté client), §4.1 et §4.2.
7. **Stories, via ledger.js uniquement**, avec un générateur de lots sur le modèle de `scratchpad/story/make-batches.mjs` :
   - decisions-3 : todo → in-progress → review → done, critères cochés, note technique (13.03, LDAP/AD en V2, preuve) ;
   - identite-2 : note « Reportée en V2 (13.03) », statut inchangé ;
   - identite-1 et identite-7 : notes d'implémentation (connexion par `auth`, enrôlement, récupération) ;
   - epic identite : Scope = « Auth locale, 2FA », Out of Scope + « LDAP / Active Directory (V2) ».
   - `node ledger.js status` doit montrer `drift: []`.
8. **Commit** sur `main` limité à ces fichiers : « Story decisions-3 : ADR 13.03, authentification locale et coffre de clés. » avec la ligne Co-Authored-By. L'epic identite a déjà une différence locale de fins de ligne ; on vérifie le diff avant de l'inclure.

## Fichiers concernés

- CRÉER `docs/10-19 Conception/13 Décisions d'architecture/13.03 Authentification locale et coffre de clés.md`
- MODIFIER `13.00 Index des ADR.md`, `11.01 Cahier des charges.md`, `12.01 Rapport d'analyse du CDC.md`
- via ledger.js : `.stories/decisions-3.md`, `identite-1.md`, `identite-2.md`, `identite-7.md`, `epics/decisions.md`, `epics/identite.md`, `sprints/sprint-1.md`
- Hors dépôt : `poc-auth.mjs`, le banc étendu et les lots JSON dans le scratchpad

## Vérification

- `node poc/poc-auth.mjs deps/node_modules` : toutes les vérifications OK, contrôles négatifs compris.
- Firefox headless : une connexion complète en environ 1,5 s ou moins sur ce PC.
- Tous les wikiliens des fichiers modifiés se résolvent (boucle `while read` + `find`, comme pour decisions-2).
- Le CDC ne contient plus de mention de LDAP/AD en V1, et aucune espace normale avant « ; » ou « : » dans les lignes ajoutées.
- `ledger.js status` : `drift: []`, `recoveryPending: false` ; `git diff --cached --stat` limité aux fichiers listés.

## Risques

- **Sel dérivé de l'e-mail** : un changement d'e-mail impose de ressaisir le mot de passe. C'est prévu dans le parcours 6.
- **Politique contrôlée seulement par le navigateur** : un client modifié peut la contourner pour son propre compte. Limite écrite dans l'ADR.
- **Réinitialisation du coffre** : une boîte mail compromise pourrait la lancer. La validation d'un administrateur et la 2FA atténuent ce risque, et l'accès aux anciens fichiers reste impossible.
- **Références normatives citées de mémoire** : la tâche 2 les vérifie avant rédaction.
