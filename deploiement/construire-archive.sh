#!/usr/bin/env bash
#
# Construit l'archive d'AMANAH à déposer sur l'hébergement cPanel (sans SSH).
#
#   bash deploiement/construire-archive.sh
#
# Part du dernier commit, pas de la copie de travail : ce qui n'est pas commité
# ne part pas. Produit dans deploiement/sortie/ :
#   - amanah-<date>.zip       : l'application, dépendances et CSS/JS compris ;
#   - env-production.txt      : le .env à compléter sur le serveur (première
#                               installation seulement — jamais dans le zip, pour
#                               qu'une mise à jour n'écrase pas le .env en place).

set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
sortie="$racine/deploiement/sortie"
horodatage="$(date +%Y%m%d-%H%M)"
travail="$(mktemp -d)"
app="$travail/amanah"
trap 'rm -rf "$travail"' EXIT

cd "$racine"

if ! git diff --quiet HEAD -- app bootstrap config database lang public resources routes composer.json composer.lock package.json; then
    echo "Attention : des modifications non commitées existent ; elles ne seront PAS dans l'archive."
fi

echo "1/5  Extraction du commit $(git rev-parse --short HEAD)"
mkdir -p "$app"
git archive HEAD | tar -x -C "$app"

# Ce qui ne sert qu'au développement, ou n'a rien à faire sur un serveur :
# old.env et le script de vidage des données de test en tête.
rm -rf "$app"/{tests,Utiles,deploiement,.claude} \
       "$app"/{old.env,vider_donnees_test.sql,Demarrer_AMANAH.bat,phpunit.xml,Checklist_Complete_AMANAH.md,.editorconfig,.gitattributes}

echo "2/5  Dépendances PHP (sans les outils de développement)"
composer install --working-dir="$app" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

echo "3/5  Feuilles de style et scripts"
npm run build --silent >/dev/null
rm -rf "$app/public/build"
cp -r "$racine/public/build" "$app/public/build"
rm -rf "$app"/{node_modules,package.json,package-lock.json,vite.config.js,tailwind.config.js,postcss.config.js}

cp "$racine/deploiement/installer.php" "$app/public/installer.php"

echo "4/5  Archive"
mkdir -p "$sortie"
archive="$sortie/amanah-$horodatage.zip"
python - "$app" "$archive" <<'PY'
import os, sys, zipfile
source, cible = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(cible, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for dossier, sous, fichiers in os.walk(source):
        rel = os.path.relpath(dossier, source)
        # Les dossiers vides comptent : storage/ doit exister tel quel.
        if rel != "." and not fichiers and not sous:
            z.writestr(rel.replace(os.sep, "/") + "/", "")
        for f in fichiers:
            chemin = os.path.join(dossier, f)
            z.write(chemin, os.path.relpath(chemin, source).replace(os.sep, "/"))
PY

echo "5/5  Modèle de .env"
envfichier="$sortie/env-production.txt"
if [ -f "$envfichier" ]; then
    echo "     $envfichier existe déjà : laissé tel quel (même clé, même jeton)."
else
    php -r '
        $modele = file_get_contents($argv[1]);
        $cle = "base64:" . base64_encode(random_bytes(32));
        $jeton = bin2hex(random_bytes(24));
        $modele = preg_replace("/^APP_KEY=.*$/m", "APP_KEY=" . $cle, $modele);
        // Sans SMTP réglé, « log » évite toute erreur d envoi ; à changer ensuite.
        $modele = preg_replace("/^MAIL_MAILER=.*$/m", "MAIL_MAILER=log", $modele);
        $modele .= "\n# Jeton de l installateur (public/installer.php). À SUPPRIMER une fois\n"
                 . "# l installation terminée.\nAMANAH_INSTALLATION_JETON=" . $jeton . "\n";
        file_put_contents($argv[2], $modele);
    ' "$racine/.env.production.example" "$envfichier"
fi

taille="$(du -h "$archive" | cut -f1)"
echo
echo "Prêt :"
echo "  $archive ($taille)"
echo "  $envfichier"
