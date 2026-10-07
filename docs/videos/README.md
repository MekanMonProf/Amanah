# Scripts des vidéos du mode d'emploi

Dix vidéos, une par sujet d'aide, dans l'ordre où elles se lisent dans
l'application. Chaque fichier donne le déroulé complet : ce qu'on voit à
l'écran, ce qu'on dit par-dessus, et ce qu'il ne faut pas oublier de montrer.

L'adresse de chaque vidéo se colle ensuite dans **Paramétrage → Aide et
support**, sur la ligne du sujet correspondant. Les trois formes d'adresse
YouTube sont acceptées, elles sont converties à l'enregistrement.

| # | Sujet | Code | Durée visée |
|---|---|---|---|
| 1 | Premiers pas | `demarrer` | 3 min |
| 2 | Mon espace investisseur | `portail` | 4 min |
| 3 | Les dossiers investisseurs | `dossiers` | 6 min |
| 4 | Achats, compléments et dons | `achats` | 6 min |
| 5 | Les dividendes | `dividendes` | 6 min |
| 6 | Radiations et versements | `radiations` | 5 min |
| 7 | Décès et successions | `successions` | 6 min |
| 8 | Importer des données | `import` | 5 min |
| 9 | Exports et relevés | `exports` | 4 min |
| 10 | Paramétrage | `parametrage` | 5 min |

Environ cinquante minutes en tout.

## Avant la première prise

**Enregistrer sur une base de démonstration, pas sur la vraie.** Chaque écran
filmé montre un nom, un téléphone, un numéro de pièce d'identité et une
position financière. Une vidéo, même « non répertoriée » sur YouTube, sort de
votre contrôle. Le dépôt porte un jeu de démonstration complet :

```bash
php artisan db:seed --class=PresentationSeeder
```

À jouer sur une base séparée — en changeant `DB_DATABASE` dans le `.env` le
temps d'enregistrer, et en le remettant ensuite.

**Préparer l'écran.**

- Fermer la messagerie et les notifications ; une bulle qui apparaît en plein
  cadre oblige à refaire la prise.
- Mettre le navigateur en plein écran, zoom à 100 %, et masquer la barre des
  favoris.
- Une résolution de 1920 × 1080. En dessous, le texte de l'application devient
  illisible une fois la vidéo recompressée par YouTube.
- Vérifier que la barre latérale est dépliée : les vidéos montrent des noms
  d'entrées, pas des icônes.

**Préparer la voix.** Lire une fois le script à haute voix avant d'enregistrer.
Le débit juste est plus lent qu'on ne croit : on décrit à quelqu'un qui
découvre, pas à soi-même.

## Comment lire un script

Chaque étape donne deux colonnes :

- **À l'écran** — ce que fait la souris, ce qui s'affiche.
- **À dire** — le texte à lire, écrit pour être prononcé et non pour être lu
  des yeux. Il est volontairement court : le silence pendant qu'on clique n'est
  pas un vide, c'est le temps que prend le spectateur pour suivre.

Les encadrés **Ne pas oublier** signalent ce qui se perd le plus souvent au
montage, et qui manque ensuite à ceux qui regardent.
