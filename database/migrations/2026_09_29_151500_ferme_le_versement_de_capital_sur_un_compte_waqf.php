<?php

use App\Models\PolitiqueInvestissement;
use Illuminate\Database\Migrations\Migration;

/**
 * Referme la dernière sortie d'argent d'un compte waqf.
 *
 * `versement_capital_radiation_possible` restait à vrai sur le waqf. Le drapeau
 * n'était pas dormant : l'écran de paiement choisit sa règle d'après la query
 * string, et `?source=radiation` posait `versementAutorise` à vrai sur un compte
 * waqf — alors que `?source=dividende` le refusait. Le solde d'un waqf pouvait
 * donc être versé en ajoutant six caractères à l'URL, ce qui contredisait la
 * règle même que la page affichait juste à côté.
 *
 * Aucune radiation waqf n'existe et il n'y en aura plus, donc ce drapeau ne
 * décrit plus aucun cas légitime : à faux, il ferme la porte au lieu de laisser
 * une catégorie de compte sans règle.
 */
return new class extends Migration
{
    public function up(): void
    {
        PolitiqueInvestissement::where('categorie', 'waqf')
            ->update(['versement_capital_radiation_possible' => false]);
    }

    public function down(): void
    {
        PolitiqueInvestissement::where('categorie', 'waqf')
            ->update(['versement_capital_radiation_possible' => true]);
    }
};
