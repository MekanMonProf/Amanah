<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\ChampDossier as Champ;
use App\Models\Investisseur;
use App\Support\Completude;
use App\Support\Droits;
use App\Support\Modules;
use Livewire\Component;

/**
 * Ce qu'un dossier doit contenir pour être dit complet.
 *
 * Le réglage ne bloque rien : un dossier auquel il manque une pièce reste
 * utilisable, il est seulement signalé. Arrêter une souscription pour un papier
 * en retard coûterait plus cher que le risque qu'on cherche à réduire — c'est le
 * choix d'origine, et cet écran ne le remet pas en cause ; il dit seulement
 * quelles pièces comptent dans le signalement.
 */
class ChampsDossier extends Component
{
    /** @var array<string, array<string, bool>> type => champ => coché */
    public array $coches = [];

    public function mount(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->charger();
    }

    private function charger(): void
    {
        foreach (Completude::CONTEXTES as $type) {
            $actifs = Champ::where('contexte', $type)->where('actif', true)->pluck('champ')->all();

            // On parcourt le catalogue, pas la table : un champ ajouté au
            // catalogue apparaît à l'écran sans qu'on écrive une migration pour lui.
            foreach (array_keys(Completude::catalogue($type)) as $champ) {
                $this->coches[$type][$champ] = in_array($champ, $actifs, true);
            }
        }
    }

    public function enregistrer(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $modifications = [];

        foreach (Completude::CONTEXTES as $type) {
            foreach (array_keys(Completude::catalogue($type)) as $champ) {
                $voulu = (bool) ($this->coches[$type][$champ] ?? false);

                $ligne = Champ::firstOrNew(['contexte' => $type, 'champ' => $champ]);

                if ($ligne->exists && $ligne->actif === $voulu) {
                    continue;
                }

                $ligne->actif = $voulu;
                $ligne->save();

                $modifications[] = sprintf('%s / %s : %s', $type, $champ, $voulu ? 'réclamé' : 'ignoré');
            }
        }

        Completude::oublier();
        $this->charger();

        if ($modifications === []) {
            session()->flash('info_champs', __("Aucun changement à enregistrer."));

            return;
        }

        AuditLog::enregistrer(
            action: 'modification_champs_dossier',
            entite: 'champ_dossier',
            apres: ['modifications' => $modifications],
        );

        session()->flash('succes_champs', __(
            ":nombre champ(s) modifié(s). :incomplets dossier(s) sont maintenant signalés incomplets.",
            [
                'nombre' => count($modifications),
                'incomplets' => Completude::filtrerIncomplets(Investisseur::query())->count(),
            ],
        ));
    }

    /** Ce que le réglage courant donnerait, pour décider en connaissance de cause. */
    public function nombreIncomplets(): int
    {
        return Completude::filtrerIncomplets(Investisseur::query())->count();
    }

    public function render()
    {
        return view('livewire.parametrage.champs-dossier', [
            'cataloguePhysique' => Completude::CATALOGUE_PHYSIQUE,
            'catalogueMorale' => Completude::CATALOGUE_MORALE,
            'catalogueSuccession' => Completude::CATALOGUE_SUCCESSION,
        ]);
    }
}
