"""
Construit les deux zips de la vitrine à partir d'une seule source, vitrine/.

    python deploiement/construire-vitrine.py

- vitrine.zip         : la version publique, pour public_html. Les blocs
                        encadrés de <!-- specimen:debut --> / <!-- specimen:fin -->
                        en sont retirés ; si un spécimen y restait, la
                        construction s'arrête plutôt que de le publier.
- vitrine-apercu.zip  : la version de relecture, spécimens compris, pour
                        public_html/apercu/.

Un bloc quitte les spécimens le jour où on retire ses deux balises, une fois
les vraies informations en place.
"""

import os
import re
import stat
import sys
import time
import zipfile

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SOURCE = os.path.join(RACINE, "vitrine")
SORTIE = os.path.join(RACINE, "deploiement", "sortie")

BLOC = re.compile(r"<!-- specimen:debut -->.*?<!-- specimen:fin -->", re.S)
BALISE = re.compile(r"<!-- specimen:(?:debut|fin) -->")


def fichiers_annexes():
    yield "traductions.js"
    for nom in sorted(os.listdir(os.path.join(SOURCE, "images"))):
        yield "images/" + nom


def entree(nom):
    # Chaque fichier reçoit explicitement les droits d'un fichier web, 644.
    # Écrite depuis la mémoire, une entrée n'en aurait aucun : extraite par
    # cPanel, la page devenait illisible pour le serveur (403). Copiés depuis
    # Windows, les autres arrivaient en 666, que certains hébergeurs refusent.
    info = zipfile.ZipInfo(nom, date_time=time.localtime()[:6])
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = (stat.S_IFREG | 0o644) << 16
    return info


def ecrire(chemin, page):
    with zipfile.ZipFile(chemin, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr(entree("index.html"), page)
        for nom in fichiers_annexes():
            with open(os.path.join(SOURCE, nom), "rb") as f:
                z.writestr(entree(nom), f.read())
    manquantes = set(re.findall(r'(?:src|href)="(images/[^"]+)"', page)) - set(fichiers_annexes())
    if manquantes:
        sys.exit("Images introuvables : %s" % ", ".join(sorted(manquantes)))


def main():
    source = open(os.path.join(SOURCE, "index.html"), encoding="utf-8").read()
    if source.count("<!-- specimen:debut -->") != source.count("<!-- specimen:fin -->"):
        sys.exit("Balises specimen:debut et specimen:fin dépareillées.")

    publique = BLOC.sub("", source)
    # Toute classe « specimen » (pas « bandeau-specimen ») trahit un oubli.
    if re.search(r'class="[^"]*(?<![\w-])specimen(?![\w-])', publique):
        sys.exit("Un spécimen reste hors balises : la version publique n'est pas construite.")
    apercu = BALISE.sub("", source)

    os.makedirs(SORTIE, exist_ok=True)
    ecrire(os.path.join(SORTIE, "vitrine.zip"), publique)
    ecrire(os.path.join(SORTIE, "vitrine-apercu.zip"), apercu)

    blocs = len(BLOC.findall(source))
    print("vitrine.zip         : version publique, %d bloc(s) spécimen retiré(s)" % blocs)
    print("vitrine-apercu.zip  : version de relecture, spécimens compris")


if __name__ == "__main__":
    main()
