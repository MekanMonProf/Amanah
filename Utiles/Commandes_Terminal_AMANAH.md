# Commandes terminal — Projet AMANAH

*Toutes à lancer depuis le dossier du projet :*
```
cd C:\Users\hp\amanah-platform
```

---

## Installation initiale (déjà fait, pour mémoire)

```
composer create-project laravel/laravel amanah-platform
composer require laravel/breeze --dev
php artisan breeze:install livewire
npm install
npm run build
```

## Bibliothèques ajoutées en cours de route

```
composer require barryvdh/laravel-dompdf        # Génération de PDF (relevés, attestations, exports)
composer require pragmarx/google2fa-qrcode      # 2FA (QR code + vérification)
composer require spatie/laravel-backup          # Sauvegardes base + fichiers
```

## Après CHAQUE nouveau fichier reçu de ma part

```
npm run build
```
*(recompile le CSS/JS — nécessaire si des classes Tailwind ont changé)*

## Après une nouvelle migration reçue

```
php artisan migrate
```

## Vérifier l'état des migrations

```
php artisan migrate:status      # Liste toutes les migrations et si elles sont passées
php artisan migrate:rollback    # Annule la dernière migration (en cas d'erreur)
```

## Vider les caches (à utiliser si un changement ne semble pas pris en compte)

```
php artisan config:clear    # Cache de configuration (.env)
php artisan route:clear     # Cache des routes
php artisan view:clear      # Cache des vues compilées
php artisan cache:clear     # Cache applicatif général
```
*Astuce : en cas de doute, lancez les 4 d'affilée.*

## Démarrer le serveur de développement

```
php artisan serve
```
Puis ouvrez `http://localhost:8000`. **Laissez cette fenêtre de terminal ouverte** (ouvrez-en une nouvelle pour taper d'autres commandes en parallèle).

## Base de données de test

```
php artisan db:seed --class=DonneesTestSeeder
```
*(à lancer après avoir vidé les données via le script `vider_donnees_test.sql` dans phpMyAdmin)*

## Créer un lien de stockage (fichiers uploadés visibles depuis le navigateur)

```
php artisan storage:link
```
*(normalement à faire une seule fois, déjà fait)*

## Tinker (exécuter du code PHP/Laravel directement, pour diagnostiquer)

```
php artisan tinker
```
Puis taper du code PHP, `exit` pour quitter.

## Sauvegardes (Spatie Backup)

```
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"   # Une seule fois, à l'installation
php artisan backup:run                                                        # Lancer une sauvegarde manuelle
php artisan backup:list                                                       # Voir les sauvegardes existantes
```

## Diagnostic des routes

```
php artisan route:list --path=investisseurs    # Voir les routes correspondant à un mot-clé
```

---

## En cas de blocage général — la combinaison "reset propre"

```
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
npm run build
```
Puis rechargez la page avec **Ctrl+Maj+R** (rechargement forcé, sans cache navigateur).
