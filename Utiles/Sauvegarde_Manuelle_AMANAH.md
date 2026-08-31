# Sauvegarde manuelle — Projet AMANAH

*À faire idéalement avant chaque étape importante (test risqué, déploiement...), ou au moins une fois par semaine tant qu'on est en développement.*

---

## Les étapes

### 1. Ouvrir un terminal dans le dossier du projet
Cliquez dans la barre d'adresse de l'Explorateur de fichiers, dans le dossier `amanah-platform`, tapez `cmd` et appuyez sur Entrée.

*(Ou : ouvrez un terminal puis `cd C:\Users\hp\amanah-platform`)*

### 2. Vérifier que MySQL tourne
Le XAMPP Control Panel doit afficher **Apache** et **MySQL** en vert. La sauvegarde a besoin de MySQL actif pour lire la base de données.

### 3. Lancer la sauvegarde
```
php artisan backup:run
```
Laissez la commande se terminer (quelques secondes à quelques minutes selon la taille des données). Vous devez voir **« Backup completed! »** à la fin, sans ligne d'erreur en rouge.

### 4. Vérifier que ça a fonctionné
```
php artisan backup:list
```
Une nouvelle ligne doit apparaître en haut du tableau, avec la date/heure d'aujourd'hui et une taille de fichier cohérente (pas 0 KB).

### 5. Copier le fichier ailleurs — l'étape la plus importante
Ouvrez :
```
amanah-platform\storage\app\private\AMANAH\
```
Copiez le fichier `.zip` le plus récent (date/heure d'aujourd'hui) vers un endroit **séparé** de cet ordinateur — clé USB, Google Drive, disque externe.

⚠️ **Une sauvegarde qui reste uniquement sur la même machine ne protège pas contre une panne de cette machine.**

---

## En résumé (une fois à l'aise)

Concrètement, ça se résume à 2 actions :
1. `php artisan backup:run`
2. Copier le fichier `.zip` généré ailleurs que sur cette machine

Le reste (étapes 2 et 4) n'est que de la vérification.
