<h2>Ce que l'import sait faire</h2>

<p>
    <strong>Import</strong> reprend un fichier Excel (<code>.xlsx</code>) ou CSV pour
    créer en une fois des gestionnaires, des investisseurs, des achats ou des
    écritures financières. Il sert aux reprises d'existant, pas à la saisie
    quotidienne.
</p>

<h2>L'ordre compte</h2>

<p>
    Les quatre familles s'importent dans cet ordre, et pas un autre :
</p>

<ol>
    <li><strong>Gestionnaires</strong></li>
    <li><strong>Investisseurs</strong></li>
    <li><strong>Achats d'actions</strong></li>
    <li><strong>Écritures financières</strong></li>
</ol>

<p>
    Chaque étape s'appuie sur la précédente : un achat a besoin de son investisseur,
    un investisseur peut être rattaché à son gestionnaire. Inverser l'ordre fait
    échouer les lignes qui référencent ce qui n'existe pas encore.
</p>

<h2>Le déroulé</h2>

<ol>
    <li><strong>Téléchargez le modèle CSV</strong> de la famille concernée. Il porte les en-têtes attendus et une ligne d'exemple à remplacer.</li>
    <li>Remplissez-le avec vos données, en gardant la première ligne telle quelle.</li>
    <li><strong>Déposez le fichier.</strong> L'application le lit et affiche un tableau de contrôle.</li>
    <li>Relisez ce tableau : il dit, ligne par ligne, ce qui sera créé, ce qui sera ignoré et pourquoi.</li>
    <li>Validez.</li>
</ol>

<p class="note">
    <strong>Rien n'est enregistré avant votre validation.</strong> Le dépôt du fichier
    ne fait que le lire. Tant que vous n'avez pas validé le tableau de contrôle, la
    base n'a pas changé — vous pouvez corriger le fichier et recommencer autant de
    fois que nécessaire.
</p>

<h2>Lire le tableau de contrôle</h2>

<p>
    Chaque ligne du fichier y figure avec son sort. Les refus sont expliqués : un
    investisseur introuvable, une date illisible, un doublon. Corrigez le fichier
    source plutôt que la base : vous garderez une trace de ce que vous avez importé.
</p>

<h2>Ce que l'import ne fait pas</h2>

<ul>
    <li>Il <strong>n'enregistre pas les dividendes</strong>. Ils se calculent depuis l'écran des dividendes, qui rejoue tout l'historique et applique les règles de bord.</li>
    <li>Il n'écrase pas un dossier existant : un doublon est signalé, pas fusionné.</li>
    <li>Il ne devine pas un format de date ambigu. Écrivez les dates en <code>JJ/MM/AAAA</code>.</li>
</ul>

<p class="note">
    <strong>Les écritures sont enregistrées dans l'ordre des lignes du fichier.</strong>
    Le solde après chaque écriture est figé au moment où elle est écrite : un fichier
    dont les lignes ne sont pas chronologiques produira des soldes intermédiaires
    déconcertants, même si le solde final est juste. Triez par date avant d'importer.
</p>
