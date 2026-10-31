{{--
    Les styles du récapitulatif, communs aux attestations qui en portent un.

    Posés dans un partiel plutôt que recopiés : trois documents les emploient,
    et un tableau de rappel doit se présenter pareil qu'on relise un achat, une
    radiation ou un versement. S'inclut à l'intérieur de la balise <style>.
--}}
/* Plus petit que le tableau de l'opération attestée : c'est un rappel, pas
   l'objet du document. */
h2.titre-recap { font-size: 12px; margin: 28px 0 8px 0; color: #047857; }
table.recap { width: 100%; border-collapse: collapse; font-size: 10px; }
table.recap th, table.recap td { padding: 5px 8px; border: 1px solid #e5e7eb; }
table.recap thead th { background-color: #f9fafb; color: #6b7280; text-align: left; font-weight: normal; }
table.recap .chiffre { text-align: right; white-space: nowrap; }
/* La ligne que le document atteste, à retrouver d'un coup d'œil dans une liste
   qui peut en compter trente. */
table.recap tr.ligne-courante td { background-color: #ecfdf5; font-weight: bold; }
table.recap tfoot td { background-color: #f9fafb; font-weight: bold; border-top: 2px solid #047857; }
.avertissement { margin-top: 10px; font-size: 11px; color: #6b7280; }
