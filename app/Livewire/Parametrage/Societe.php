<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\ParametreSociete;
use App\Support\Droits;
use App\Support\Modules;
use App\Support\Telephone;
use Livewire\Component;

/**
 * Tout ce que la maison dit d'elle-même.
 *
 * Ces informations vivaient dans le `.env`, dans `config/societe.php` et dans
 * des chaînes écrites en dur au milieu de l'en-tête des PDF. Changer de raison
 * sociale ou de numéro demandait donc de toucher au code et de redéployer :
 * ce n'est pas un réglage, c'est une rustine.
 *
 * Un champ laissé vide n'efface rien : la valeur d'avant continue de
 * s'appliquer (voir ParametreSociete::valeur). L'écran peut donc rester
 * vierge, et il l'est au premier démarrage.
 */
class Societe extends Component
{
    public string $nom = '';

    public string $raisonSociale = '';

    public string $activite = '';

    public string $oeuvre = '';

    public string $rccm = '';

    public string $ninea = '';

    public string $adresse = '';

    public string $ville = '';

    public string $pays = '';

    public string $telephone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $siteWeb = '';

    public string $horaires = '';

    /** Les colonnes, dans l'ordre du formulaire. propriété => colonne */
    private const CHAMPS = [
        'nom' => 'nom',
        'raisonSociale' => 'raison_sociale',
        'activite' => 'activite',
        'oeuvre' => 'oeuvre',
        'rccm' => 'rccm',
        'ninea' => 'ninea',
        'adresse' => 'adresse',
        'ville' => 'ville',
        'pays' => 'pays',
        'telephone' => 'telephone',
        'whatsapp' => 'whatsapp',
        'email' => 'email',
        'siteWeb' => 'site_web',
        'horaires' => 'horaires',
    ];

    public function mount(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $reglages = ParametreSociete::actuel();

        foreach (self::CHAMPS as $propriete => $colonne) {
            $this->{$propriete} = (string) ($reglages->{$colonne} ?? '');
        }
    }

    protected function rules(): array
    {
        return [
            'nom' => ['nullable', 'string', 'max:255'],
            'raisonSociale' => ['nullable', 'string', 'max:255'],
            'activite' => ['nullable', 'string', 'max:255'],
            'oeuvre' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:60'],
            'ninea' => ['nullable', 'string', 'max:60'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:120'],
            'pays' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'siteWeb' => ['nullable', 'url', 'max:255'],
            'horaires' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function enregistrer(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->validate();

        // Les numéros sont rangés au format international, comme partout
        // ailleurs : un numéro tapé « 77 123 45 67 » doit ouvrir WhatsApp.
        $numeros = [];

        foreach (['telephone', 'whatsapp'] as $champ) {
            if ($this->{$champ} === '') {
                $numeros[$champ] = null;

                continue;
            }

            $normalise = Telephone::normaliser($this->{$champ});

            if ($normalise === null) {
                $this->addError($champ, __("Ce numéro n'est pas reconnaissable."));

                return;
            }

            $numeros[$champ] = $normalise;
        }

        $reglages = ParametreSociete::actuel();
        $avant = $reglages->only(array_values(self::CHAMPS));

        $valeurs = [];

        foreach (self::CHAMPS as $propriete => $colonne) {
            $valeurs[$colonne] = match ($colonne) {
                'telephone' => $numeros['telephone'],
                'whatsapp' => $numeros['whatsapp'],
                default => trim($this->{$propriete}) !== '' ? trim($this->{$propriete}) : null,
            };
        }

        $reglages->update($valeurs);

        $this->telephone = $numeros['telephone'] ?? '';
        $this->whatsapp = $numeros['whatsapp'] ?? '';

        // Le journal garde les colonnes qui ont changé, pas leur contenu :
        // ce sont des coordonnées, elles n'ont pas à être recopiées à chaque
        // modification dans une table que personne n'élague.
        $modifiees = array_keys(array_diff_assoc(
            array_map(fn ($v) => (string) $v, $valeurs),
            array_map(fn ($v) => (string) $v, $avant),
        ));

        AuditLog::enregistrer(
            action: 'modification_informations_societe',
            entite: 'parametre_societe',
            entiteId: $reglages->id,
            apres: ['champs_modifies' => $modifiees],
        );

        session()->flash('succes_parametrage', $modifiees === []
            ? __("Aucun changement à enregistrer.")
            : __(":nombre information(s) de la société enregistrée(s).", ['nombre' => count($modifiees)]));
    }

    public function render()
    {
        return view('livewire.parametrage.societe', [
            'reglages' => ParametreSociete::actuel(),
        ]);
    }
}
