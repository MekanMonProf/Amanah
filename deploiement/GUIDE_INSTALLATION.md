# Installer AMANAH sur amanah.waqfdolelxamxam.sn

Hébergement Wanekoo, cPanel, **sans SSH**. Tout se fait depuis le navigateur :
le gestionnaire de fichiers de cPanel, phpMyAdmin, et une page d'installation
à usage unique.

Durée : une heure environ. Rien dans ce guide ne touche aux fichiers de
l'ancienne application ni à ses tables.

## Ce qu'il vous faut

Dans `deploiement/sortie/`, sur votre ordinateur :

- **`amanah-AAAAMMJJ-HHMM.zip`** — l'application complète (environ 17 Mo) ;
- **`env-production.txt`** — le fichier de configuration, déjà préparé avec la
  clé de chiffrement et le jeton d'installation. **Il contient des secrets** :
  ne l'envoyez à personne, ne le mettez ni sur WhatsApp ni dans un e-mail.

Pour reconstruire l'archive après une modification d'AMANAH :
`bash deploiement/construire-archive.sh`.

---

## Étape 0 — Sauvegarder la base

AMANAH va poser ses tables dans la base de l'ancienne application. On garde
donc une copie de cette base avant tout.

1. cPanel → **phpMyAdmin**.
2. À gauche, cliquez sur la base de l'ancienne application
   (`waqfdole_…`).
3. Onglet **Exporter** → méthode **Rapide**, format **SQL** → **Exécuter**.
4. Gardez le fichier téléchargé en lieu sûr. Il contient les données des
   actionnaires.

## Étape 1 — Un utilisateur MySQL pour AMANAH

Il faut un identifiant et un mot de passe pour la base. **Ne changez pas le mot
de passe de l'utilisateur existant** : l'ancienne application s'arrêterait.
On en crée un second, réservé à AMANAH.

1. cPanel → **Bases de données MySQL**.
2. Section **Ajouter un nouvel utilisateur** : nom `amanah`, mot de passe
   avec le **Générateur de mot de passe**. Notez le nom complet affiché
   (`waqfdole_amanah`) et le mot de passe.
3. Section **Ajouter un utilisateur à une base de données** : l'utilisateur
   `waqfdole_amanah`, la base de l'ancienne application → **Ajouter**.
4. Cochez **TOUS LES PRIVILÈGES** → **Effectuer des modifications**.

## Étape 2 — Déposer l'application

1. cPanel → **Gestionnaire de fichiers**.
2. Ouvrez le dossier **`amanah.waqfdolelxamxam.sn`** (à côté de `public_html`,
   pas dedans). Il contient déjà un dossier `public` vide : c'est normal.
3. **Charger** → choisissez le fichier `amanah-….zip`. Attendez la barre verte
   à 100 %.
4. Revenez au dossier, clic droit sur le zip → **Extract** (Extraire) → laissez
   le chemin proposé → **Extract File(s)**.
5. Vérifiez : le dossier `amanah.waqfdolelxamxam.sn` contient maintenant
   `app`, `bootstrap`, `config`, `public`, `vendor`, `storage`… directement,
   sans sous-dossier intermédiaire.
6. Supprimez le fichier zip.

## Étape 3 — Créer le fichier .env

1. Gestionnaire de fichiers → **Paramètres** (en haut à droite) → cochez
   **Afficher les fichiers cachés** → **Save**.
2. Toujours dans `amanah.waqfdolelxamxam.sn` (**pas** dans `public`) :
   **+ Fichier** → nom : `.env` → **Create New File**.
3. Clic droit sur `.env` → **Edit** (Modifier). Collez tout le contenu de
   `env-production.txt`.
4. Complétez ces lignes :

   | Ligne | Valeur |
   |---|---|
   | `DB_DATABASE=` | le nom de la base de l'ancienne application (`waqfdole_…`) |
   | `DB_USERNAME=` | `waqfdole_amanah` (étape 1) |
   | `DB_PASSWORD=` | le mot de passe de l'étape 1 |
   | `AMANAH_ADMIN_EMAIL=` | l'e-mail du premier administrateur d'AMANAH |
   | `AMANAH_ADMIN_NOM=` | son nom |
   | `AMANAH_ADMIN_PRENOM=` | son prénom |

   Si le mot de passe contient un `#`, un espace ou un `"`, mettez-le entre
   guillemets : `DB_PASSWORD="…"`.
5. **Save Changes**.

Ne touchez pas aux lignes `APP_KEY` ni `AMANAH_INSTALLATION_JETON` : elles sont
déjà remplies.

## Étape 4 — Ouvrir l'installateur

Ouvrez **https://amanah.waqfdolelxamxam.sn/installer.php**.

Le tableau **Environnement du serveur** doit être entièrement vert. Les
extensions « conseillées » peuvent être absentes.

**Si la ligne PHP est rouge** (version inférieure à 8.2) :

- cPanel → **MultiPHP Manager** → cochez **seulement**
  `amanah.waqfdolelxamxam.sn` → version **PHP 8.2** ou **8.3** → **Appliquer**.
  Rechargez la page de l'installateur.
- Si vous ne trouvez que **« Sélectionner une version de PHP »**, ne changez
  rien et prévenez-moi : ce réglage vaut pour tout le compte, et l'ancienne
  application pourrait ne pas supporter une version plus récente.

**Si une autre ligne est rouge**, notez-la et prévenez-moi.

## Étape 5 — Installer

Dans le champ **Jeton d'installation**, collez la valeur de la ligne
`AMANAH_INSTALLATION_JETON` du `.env`. Puis cliquez les boutons **dans l'ordre**,
en lisant le résultat de chacun avant de passer au suivant :

1. **Vérifier la base** — doit dire « Connexion … réussie » et
   « Tables de l'ancienne application trouvées : oui ».
2. **1. Créer les tables d'AMANAH** — une longue liste de lignes `DONE`.
3. **2. Poser les paramètres et l'administrateur** — affiche
   **« Mot de passe temporaire : ADM-… »**. **Notez-le immédiatement** : il ne
   sera plus jamais affiché.
4. **3. Simuler la reprise des comptes** — n'écrit rien. Doit annoncer
   1 administrateur, 5 secrétaires et 412 actionnaires. Lisez la liste des
   comptes « à arbitrer » qu'elle affiche (18 lors de la répétition : des
   comptes sans e-mail ni téléphone propre) : prenez-la en photo ou copiez-la, elle servira à
   compléter ces dossiers ensuite.
5. **4. Reprendre les comptes** — même bilan, écrit cette fois.
6. **5. Contrôler la cohérence** — doit finir par **« Tout est cohérent. »**

Si un bouton affiche **échec**, arrêtez-vous et envoyez-moi le texte affiché.
Rien n'est perdu : la reprise est « tout ou rien », et une étape réussie peut
être relancée sans créer de doublon.

## Étape 6 — Refermer l'installateur

1. Cliquez **Terminer et supprimer l'installateur** et confirmez.
2. Dans le `.env`, supprimez la ligne `AMANAH_INSTALLATION_JETON=…` (et les
   deux lignes de commentaire au-dessus). **Save Changes**.

Sans cette ligne, l'installateur refuse de s'ouvrir, même s'il revenait par
une mise à jour.

## Étape 7 — Vérifier

1. **https://amanah.waqfdolelxamxam.sn/login** → connectez-vous avec l'e-mail
   administrateur et le mot de passe `ADM-…`. AMANAH demande aussitôt d'en
   choisir un nouveau.
2. Dans **Investisseurs**, vous devez trouver les 412 actionnaires.
3. Demandez à un actionnaire de se connecter avec **son ancien numéro et son
   ancien mot de passe** : ils fonctionnent tels quels dans AMANAH.
4. Vérifiez que **https://waqfdolelxamxam.sn/login.php** (l'ancienne
   application) marche toujours.

---

## Plus tard

### Activer l'envoi des e-mails

Au départ, `MAIL_MAILER=log` : AMANAH n'envoie aucun e-mail (les identifiants
partent par WhatsApp), et rien ne casse. Pour activer l'envoi :

1. cPanel → **Comptes de messagerie** → créez `noreply@waqfdolelxamxam.sn`.
2. **Connect Devices** sur ce compte : relevez le serveur SMTP sortant et le
   port.
3. Dans le `.env` :

   ```
   MAIL_MAILER=smtp
   MAIL_HOST=mail.waqfdolelxamxam.sn
   MAIL_PORT=465
   MAIL_USERNAME=noreply@waqfdolelxamxam.sn
   MAIL_PASSWORD="le mot de passe de la boîte"
   MAIL_FROM_ADDRESS=noreply@waqfdolelxamxam.sn
   ```

### Mettre à jour AMANAH

1. Sur l'ordinateur : `bash deploiement/construire-archive.sh`.
2. Faites une sauvegarde de la base (étape 0).
3. Déposez et extrayez le nouveau zip au même endroit, en acceptant
   d'écraser. Le `.env` n'est pas dans le zip : il reste intact.
4. Si la mise à jour ajoute des tables : remettez temporairement la ligne
   `AMANAH_INSTALLATION_JETON` dans le `.env` (même valeur que dans
   `env-production.txt`), ouvrez `installer.php`, cliquez seulement
   **1. Créer les tables d'AMANAH**, puis **Terminer** et retirez la ligne.
   Sinon, supprimez simplement `public/installer.php`.

### Remonter les dossiers saisis en local

Les dossiers travaillés sur l'ordinateur pendant la répétition peuvent remplacer
ceux de la base en ligne. Un export complet de la base locale ne convient pas :
en ligne, AMANAH et l'ancienne application partagent une même base, et il
écraserait aussi les quatorze tables de l'ancienne — qui tourne toujours, et
dont les données sont plus récentes que la copie locale. Le script ne sort que
les tables d'AMANAH, et nomme ce qu'il laisse.

```bash
php deploiement/exporter-tables-amanah.php
```

Le fichier arrive dans `deploiement/sortie/`, volontairement hors du dépôt : il
contient les noms et les téléphones de vrais actionnaires.

Puis dans phpMyAdmin, sur `waqfdole_gestionactionnaires_bd` : la sauvegarde de
l'étape 0 d'abord, ensuite onglet **Importer** → le fichier `.sql` → **Exécuter**.

**Chaque table d'AMANAH est supprimée et refaite.** Tout ce qui aurait été saisi
en ligne depuis l'export est perdu. Vérifiez d'abord que personne n'y a
travaillé.

**Les comptes de connexion deviennent ceux de l'ordinateur.** L'administrateur
créé à l'étape 5 et son mot de passe `ADM-…` disparaissent : connectez-vous
ensuite avec les comptes locaux.

Deux comptes dont le mot de passe est écrit dans le dépôt — public — sont
neutralisés à l'export. Le compte de démonstration est désactivé. L'autre,
reconnaissable à son adresse `gestionnaire.essai@local.test`, n'est un compte
d'essai que par son nom : il porte le profil de la gestionnaire qui suit
183 dossiers. Il garde donc son accès, mais repart avec un mot de passe que
personne ne connaît. **Réinitialisez-le depuis sa fiche et transmettez-le-lui**,
sinon elle ne peut plus entrer.

### En cas de page d'erreur

AMANAH n'affiche jamais le détail d'une erreur aux visiteurs. Le détail est
dans `amanah.waqfdolelxamxam.sn/storage/logs/laravel.log` : ouvrez-le dans le
gestionnaire de fichiers et envoyez-moi les dernières lignes.

### Les deux applications en parallèle

Après la reprise, l'ancienne application continue de fonctionner, mais elle
et AMANAH ne se synchronisent pas : une fiche modifiée dans l'une ne l'est pas
dans l'autre. Convenez d'une date à partir de laquelle les secrétaires
travaillent uniquement dans AMANAH.
