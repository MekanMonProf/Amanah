# Base de démonstration

Une base séparée, peuplée de dossiers fictifs, pour enregistrer les vidéos du
mode d'emploi et montrer l'application sans exposer les vrais actionnaires.

Chaque écran filmé porte un nom, un téléphone, un numéro de pièce d'identité et
une position financière. Une vidéo échappe à son auteur : ce qui y figure y
figure pour toujours, même sur une chaîne « non répertoriée ».

## La construire

```bash
php database/demo/preparer.php
```

Le script crée `amanah_demo`, y joue les migrations, les paramètres initiaux,
puis `PresentationSeeder`. Il refuse de travailler sur la base déclarée dans le
`.env`, et refuse un nom de base qui ne contient pas « demo » — `PresentationSeeder`
commence par vider les tables métier, et le lancer sur la mauvaise base
effacerait la reprise du classeur.

Un autre nom se passe en argument : `php database/demo/preparer.php amanah_demo_2`.

Rejouer le script sur une base existante la refait entièrement, après
confirmation. C'est le geste à faire quand une prise a sali le jeu de données.

## Basculer

```bash
php database/demo/basculer.php            # dit où l'on en est
php database/demo/basculer.php demo       # passe à la démonstration
php database/demo/basculer.php reelle     # revient aux vrais dossiers
```

Sans argument, le script annonce seulement la base active. **C'est le geste à
faire avant de lancer un enregistrement**, et avant toute opération sur de
vraies données.

Le nom de la base réelle est gardé dans une ligne commentée du `.env`
(`# AMANAH_BASE_REELLE=`), pour que le retour ne dépende pas de la mémoire de
qui a basculé — même des semaines plus tard.

Si vous mettez la configuration en cache, videz-la après chaque bascule :

```bash
php artisan config:clear
```

## Se connecter à la démonstration

Les comptes du `.env` et les comptes d'essai n'existent pas dans cette base :
elle a les siens, créés par le seeder.

| | |
|---|---|
| Administrateur | `administrateur@anddox.sn` |
| Mot de passe commun | `Amanah2026!` |

Le seeder affiche la liste des comptes à la fin de son exécution. Ces
identifiants ne valent que sur une installation locale.

## Ce que le jeu contient

Onze mois d'activité, de novembre 2025 à aujourd'hui, joués à leur date par les
méthodes du domaine — les soldes et les réinvestissements sont donc ceux
qu'aurait produits un usage réel.

| | |
|---|---|
| Investisseurs | 34, dont 4 personnes morales et 10 dossiers incomplets |
| Décédés | 2 — une succession réglée, une en cours |
| Gestionnaires | 3, avec 3 transferts de portefeuille |
| Comptes | 35 |
| Achats | 178, dont 3 présents au waqf |
| Écritures | 429 |
| Barèmes | 22, avec une correction rétroactive |
| Dividendes | 251 |
| Radiations | 4, à divers stades |
| Dons | 2 |

Il couvre volontairement ce qui fait parler une démonstration : la diaspora et
ses numéros étrangers, les dossiers incomplets, les personnes morales, les
présents au waqf caritatif, une correction de barème, et des radiations payées
comme d'autres encore en attente.

## Ce qu'il faut garder en tête

**La bascule ne touche que la base de données.** Les fichiers déposés — pièces
d'identité scannées, conventions signées — vivent dans `storage/`, partagé par
les deux. Un document ouvert depuis un dossier de démonstration peut donc ne
rien afficher, ou afficher un fichier réel si son chemin coïncide. Ne filmez pas
l'ouverture d'une pièce jointe.

**Les réglages de la société sont propres à chaque base.** Le numéro de support,
les horaires et les adresses de vidéos saisis sur la base réelle ne suivent pas.
À renseigner une fois sur la démonstration si une vidéo doit les montrer.

**Le serveur de développement lit le `.env` au démarrage.** Après une bascule,
arrêtez-le et relancez-le, sinon il continue de servir l'ancienne base.
