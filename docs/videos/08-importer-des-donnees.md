# 8 — Importer des données

**Code du sujet** : `import` · **Durée visée** : 5 minutes

**Pour qui** : l'administration. Opération rare mais lourde : on y reprend des
centaines de lignes d'un coup, et une erreur d'ordre fait tout échouer.

## Avant de filmer

- Préparer **deux fichiers CSV** à l'avance, sur le bureau :
  - un **propre**, cinq ou six lignes d'investisseurs ;
  - un **volontairement fautif** — un investisseur introuvable, une date
    illisible, un doublon. C'est le fichier qui fait la valeur de la vidéo.
- Les ouvrir une fois dans un tableur avant d'enregistrer, pour vérifier qu'ils
  donnent bien ce qu'on veut montrer.
- Ne **pas valider** l'import du fichier fautif.

## Déroulé

### Ouverture — 0:00

| À l'écran | À dire |
|---|---|
| L'écran Import. | « L'import sert à reprendre un existant : un fichier Excel ou CSV, et des centaines de lignes créées d'un coup. Ce n'est pas un outil de saisie quotidienne. » |

### L'ordre — 0:20

| À l'écran | À dire |
|---|---|
| La souris longe les quatre onglets, de gauche à droite. | « Quatre familles, et elles s'importent dans cet ordre : gestionnaires, investisseurs, achats, écritures. » |
| S'arrêter. | « L'ordre n'est pas décoratif. Un achat a besoin de son investisseur ; un investisseur peut être rattaché à son gestionnaire. Si vous commencez par les achats, toutes les lignes qui pointent vers quelqu'un qui n'existe pas encore échoueront. » |

### Le modèle — 0:55

| À l'écran | À dire |
|---|---|
| Cliquer sur « Télécharger le modèle CSV ». Ouvrir le fichier. | « Pour chaque famille, un modèle. Il porte les en-têtes attendus et une ligne d'exemple. » |
| Montrer la première ligne, puis la seconde. | « Gardez la première ligne telle quelle : ce sont les en-têtes, l'application s'en sert pour reconnaître les colonnes. Remplacez la seconde par vos données. » |
| Montrer une date dans le fichier. | « Et écrivez les dates en jour/mois/année. L'application ne devine pas un format ambigu : pour elle, zéro trois barre zéro quatre peut être mars ou avril, elle ne tranchera pas à votre place. » |

### Le dépôt et le contrôle — 1:50

| À l'écran | À dire |
|---|---|
| Déposer le fichier propre. Le tableau de contrôle s'affiche. | « On dépose le fichier. L'application le lit et montre ce qu'elle a compris. » |

> **Ne pas oublier.** Le point qui lève l'inquiétude :
>
> « Rien n'est enregistré à ce stade. Déposer le fichier, c'est seulement le
> lire. Tant que vous n'avez pas validé ce tableau, la base n'a pas bougé. »

| À l'écran | À dire |
|---|---|
| Parcourir le tableau ligne à ligne. | « Chaque ligne du fichier est là, avec ce qui va lui arriver. » |
| Valider. Le résultat s'affiche. | « Et on valide. » |

### Le fichier fautif — 3:00

| À l'écran | À dire |
|---|---|
| Déposer le second fichier. Le tableau montre les refus. | « Maintenant un fichier qui a des défauts — c'est le cas normal, la première fois. » |
| S'arrêter sur la ligne « investisseur introuvable ». | « Celle-ci référence un investisseur qui n'existe pas. » |
| S'arrêter sur la date illisible. | « Celle-ci a une date que l'application ne sait pas lire. » |
| S'arrêter sur le doublon. | « Et celle-ci existe déjà. Elle est signalée, pas fusionnée : l'import n'écrase jamais un dossier existant. » |
| Ne pas valider. Fermer. | « On ne valide pas. On corrige le fichier et on recommence. » |

> **Ne pas oublier.**
>
> « Corrigez le fichier source, pas la base. Vous garderez ainsi une trace de ce
> que vous avez importé, et vous pourrez recommencer autant de fois qu'il faut. »

### Ce que l'import ne fait pas — 4:10

| À l'écran | À dire |
|---|---|
| Montrer l'encadré de l'écran des achats. | « Deux limites à connaître. » |
| — | « L'import n'enregistre pas les dividendes. Ils se calculent depuis l'écran des dividendes, qui rejoue tout l'historique et applique les règles de bord. N'essayez pas de les importer à la main. » |
| Ouvrir un fichier d'écritures dans le tableur, montrer la colonne des dates. | « Et les écritures sont enregistrées dans l'ordre des lignes du fichier, pas dans l'ordre des dates. Le solde après chaque écriture est figé au moment où elle est écrite. » |
| Trier le fichier par date dans le tableur. | « Donc si vos lignes ne sont pas chronologiques, vous obtiendrez des soldes intermédiaires déconcertants, même si le total final est juste. Triez par date avant d'importer. » |

### Clôture — 4:50

| À l'écran | À dire |
|---|---|
| L'écran Import, propre. | « L'ordre des quatre familles, les dates en jour/mois/année, et un tri chronologique pour les écritures. Avec ça, un import se passe bien. » |

## À ne pas montrer

- Un fichier contenant de vraies données d'actionnaires ouvert dans le tableur :
  le nom du fichier et son contenu apparaissent en clair.
- Le chemin complet d'un dossier personnel dans la boîte de dialogue de fichier.
