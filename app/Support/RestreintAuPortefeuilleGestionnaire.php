<?php

namespace App\Support;

use App\Models\CompteInvestissement;
use App\Models\Investisseur;
use Illuminate\Support\Facades\Auth;

/**
 * Les routes ne filtrent que par rôle (gestionnaire, direction...), jamais par
 * portefeuille : sans ce contrôle, un gestionnaire connecté peut agir sur n'importe
 * quel investisseur en changeant l'ID dans l'URL, même hors de son portefeuille.
 */
trait RestreintAuPortefeuilleGestionnaire
{
    protected function assurerAccesGestionnaire(Investisseur $investisseur): void
    {
        $user = Auth::user();

        if ($user->role === 'gestionnaire' && $investisseur->gestionnaire_id !== $user->gestionnaire?->id) {
            abort(403, "Cet investisseur ne fait pas partie de votre portefeuille.");
        }
    }

    protected function assurerAccesGestionnairePourCompte(CompteInvestissement $compte): void
    {
        $this->assurerAccesGestionnaire($compte->investisseur);
    }
}
