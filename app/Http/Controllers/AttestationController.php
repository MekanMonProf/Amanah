<?php

namespace App\Http\Controllers;

use App\Models\AchatAction;
use App\Models\CompteInvestissement;
use App\Models\EcritureCompteFinancier;
use App\Models\Radiation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class AttestationController extends Controller
{
    use \App\Http\Controllers\Concerns\RendPdf;

    /**
     * Un investisseur ne peut voir que les attestations de son propre compte.
     * Le personnel (tous les autres rôles) peut voir n'importe laquelle — l'accès aux
     * pages de gestion elles-mêmes est déjà filtré par le middleware de rôle.
     */
    protected function verifierAcces(CompteInvestissement $compte): void
    {
        $user = Auth::user();

        if ($user->role === 'investisseur' && $compte->investisseur_id !== optional($user->investisseurLie)->id) {
            abort(403);
        }
    }

    public function achat(AchatAction $achat)
    {
        $achat->load('compte.investisseur', 'offertPar');

        // Une present (hommage à un défunt ou cadeau à un vivant) est portée au compte du Waqf :
        // c'est le donateur, et non le titulaire du compte, qui a droit à l'attestation.
        if ($achat->estUnPresent()) {
            $user = Auth::user();
            if ($user->role === 'investisseur' && $achat->offert_par_investisseur_id !== optional($user->investisseurLie)->id) {
                abort(403);
            }
        } else {
            $this->verifierAcces($achat->compte);
        }

        $historique = $this->historiqueDesAchats($achat);

        $pdf = Pdf::loadView('pdf.attestation-achat', [
            'achat' => $achat,
            'compte' => $achat->compte,
            'investisseur' => $achat->compte->investisseur,
            'historique' => $historique,
            'actionsAcquises' => (int) $historique->sum('nombre_actions'),
            'montantCumule' => (float) $historique->sum('montant'),
            // La part venue des dividendes réinvestis. Le cumul ci-dessus la
            // comprend, alors qu'elle ne correspond à aucun versement : dite à
            // part, elle évite de laisser croire à l'investisseur qu'il a sorti
            // cette somme de sa poche.
            'reinvesti' => $historique->filter(fn (AchatAction $ligne) => $ligne->estUnReinvestissement()),
            // Ce que le compte détenait réellement ce jour-là — radiations et
            // dons déduits. Sans objet pour un présent : le compte est celui du
            // Waqf, et son volume ne regarde pas le donateur.
            'actionsDetenues' => $achat->estUnPresent()
                ? null
                : $achat->compte->nombreActionsAu($achat->date_achat->toDateString()),
            'periodesEnAttente' => $this->periodesSansBareme($achat),
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        // Le nom du fichier suit le titre du document : un présent Waqf n'est pas une
        // attestation d'achat ordinaire, et le destinataire le classe sous ce nom.
        $prefixe = match (true) {
            $achat->estUnPresent() => 'Certificat_Hommage_Generosite_',
            $achat->estUnReinvestissement() => 'Attestation_Reinvestissement_',
            default => 'Attestation_Achat_',
        };

        return $this->rendrePdf($pdf, $prefixe . $achat->numero_achat . '.pdf');
    }

    /**
     * Ce qui a été acquis avant cet achat, et lui-même pour finir.
     *
     * Les actionnaires demandaient à retrouver leurs achats antérieurs sur
     * l'attestation : une souscription se fait souvent en plusieurs fois, et
     * l'attestation d'un versement isolé ne disait rien de l'ensemble.
     *
     * Deux précautions s'imposent, et elles tiennent toutes deux au présent
     * waqf. Ses actions sont portées au compte institutionnel du Waqf, partagé
     * par tous les donateurs : y lire « les achats du compte » reviendrait à
     * montrer à chaque donateur les dons de tous les autres. On s'en tient donc
     * aux siens. Pour les mêmes raisons, le volume du compte ne lui est pas
     * montré — voir l'appel plus haut.
     *
     * L'arrêt se fait à la date de l'achat et non à aujourd'hui : une
     * attestation dit ce qui était vrai le jour où elle a été méritée, et deux
     * tirages du même document doivent se ressembler. Le tri suit la date puis
     * l'identifiant, deux achats d'un même jour gardant ainsi l'ordre de leur
     * saisie.
     */
    protected function historiqueDesAchats(AchatAction $achat): \Illuminate\Support\Collection
    {
        $requete = AchatAction::where('compte_id', $achat->compte_id)
            ->whereDate('date_achat', '<=', $achat->date_achat->toDateString());

        if ($achat->estUnPresent()) {
            $requete->where('offert_par_investisseur_id', $achat->offert_par_investisseur_id);
        }

        return $requete->orderBy('date_achat')->orderBy('id')->get()
            // Un achat saisi après celui-ci, mais daté du même jour, se glisse
            // sinon dans un document censé lui être antérieur.
            ->reject(fn (AchatAction $ligne) => $ligne->date_achat->toDateString() === $achat->date_achat->toDateString()
                && $ligne->id > $achat->id)
            ->values();
    }

    /**
     * Les mois dont le dividende n'est pas encore arrêté, à la date attestée.
     *
     * Un achat par réinvestissement est daté du jour où le barème est saisi, et
     * non du mois qu'il rémunère — voir
     * CompteInvestissement::acheterActionsAvecSoldeDisponible(). Tant qu'un
     * mois n'est pas arrêté, les actions qui en sortiront n'existent pas : le
     * récapitulatif est exact, mais il n'est pas complet, et il le restera
     * puisque ces actions porteront la date de leur enregistrement.
     *
     * Le dire évite au porteur de chercher un réinvestissement qui n'a pas
     * encore eu lieu. Au 31 octobre 2026, la base de répétition accusait deux
     * mois de retard ; ce n'est donc pas un cas d'école.
     *
     * On s'arrête au mois précédant l'achat attesté : le mois courant n'est
     * jamais arrêté, et l'annoncer comme un manque serait une fausse alerte.
     *
     * @return list<string> des mois « AAAA-MM », du plus ancien au plus récent
     */
    protected function periodesSansBareme(AchatAction $achat): array
    {
        // Un présent va au compte du Waqf, dont la catégorie ne verse pas de
        // dividendes : la question ne se pose pas.
        if ($achat->estUnPresent() || ! $achat->compte->politique()?->eligible_dividendes) {
            return [];
        }

        $premier = $achat->compte->achats()->min('date_achat');

        if ($premier === null) {
            return [];
        }

        $mois = \Illuminate\Support\Carbon::parse($premier)->startOfMonth();
        $dernier = $achat->date_achat->copy()->startOfMonth()->subMonth();

        $arretes = \App\Models\BaremeDividende::where('categorie', $achat->compte->categorie)
            ->pluck('periode')
            ->map(fn ($periode) => \Illuminate\Support\Carbon::parse($periode)->format('Y-m'))
            ->all();

        $attente = [];

        while ($mois <= $dernier) {
            if (! in_array($mois->format('Y-m'), $arretes, true)) {
                $attente[] = $mois->format('Y-m');
            }
            $mois->addMonth();
        }

        return $attente;
    }

    public function radiation(Radiation $radiation)
    {
        $radiation->load('compte.investisseur');
        $this->verifierAcces($radiation->compte);

        $montantVerse = $this->montantVerse($radiation);
        $historique = $this->historiqueDesRadiations($radiation);

        $pdf = Pdf::loadView('pdf.attestation-radiation', [
            'radiation' => $radiation,
            'compte' => $radiation->compte,
            'investisseur' => $radiation->compte->investisseur,
            'montantVerse' => $montantVerse,
            'statutPaiement' => $montantVerse >= $radiation->montant_total ? 'Payé' : ($montantVerse > 0 ? 'Partiellement payé' : 'Non payé'),
            'historique' => $historique,
            'actionsSorties' => (int) $historique->sum('nombre_actions_radiees'),
            'capitalSorti' => (float) $historique->sum('montant_total'),
            // Ce qui reste après cette sortie : la question que pose quiconque
            // ne radie qu'une partie de ses actions.
            'actionsRestantes' => $radiation->compte->nombreActionsAu($radiation->date_radiation->toDateString()),
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        $prefixe = $radiation->estUneSuccession()
            ? 'Attestation_Liquidation_Succession_'
            : 'Attestation_Radiation_';

        return $this->rendrePdf($pdf, $prefixe . $radiation->numero_radiation . '.pdf');
    }

    /**
     * Les sorties antérieures du compte, et celle-ci pour finir.
     *
     * Même raison que pour les achats, et même arrêt à la date du document :
     * un compte se vide rarement d'un coup — treize des quarante-six comptes
     * radiés l'ont été en plusieurs fois — et l'attestation d'une sortie
     * isolée ne disait pas où en était le compte.
     *
     * Les liquidations de succession ne sont pas séparées des radiations
     * ordinaires : elles sortent du même capital et se lisent dans la même
     * suite. Leur numéro les distingue, préfixé — voir
     * Radiation::estUneSuccession().
     */
    protected function historiqueDesRadiations(Radiation $radiation): \Illuminate\Support\Collection
    {
        return Radiation::where('compte_id', $radiation->compte_id)
            ->whereDate('date_radiation', '<=', $radiation->date_radiation->toDateString())
            ->orderBy('date_radiation')->orderBy('id')->get()
            ->reject(fn (Radiation $ligne) => $ligne->date_radiation->toDateString() === $radiation->date_radiation->toDateString()
                && $ligne->id > $radiation->id)
            ->values();
    }

    /**
     * Les versements antérieurs du compte, et celui-ci pour finir.
     *
     * Seul le type « paiement » est retenu. Un compte reçoit aussi des
     * dividendes et des compléments : ce sont des entrées, les mêler aux
     * sorties fausserait le total, et le relevé mensuel les porte déjà tous.
     *
     * Ce rappel ne figure que sur l'attestation, jamais sur le reçu : le reçu
     * s'ouvre par un lien signé, sans connexion, qu'un destinataire peut
     * transférer. Il doit continuer de ne parler que de son opération.
     */
    protected function historiqueDesVersements(EcritureCompteFinancier $ecriture): \Illuminate\Support\Collection
    {
        return EcritureCompteFinancier::where('compte_id', $ecriture->compte_id)
            ->where('type_ecriture', 'paiement')
            ->whereDate('date_ecriture', '<=', $ecriture->date_ecriture->toDateString())
            ->orderBy('date_ecriture')->orderBy('id')->get()
            ->reject(fn (EcritureCompteFinancier $ligne) => $ligne->date_ecriture->toDateString() === $ecriture->date_ecriture->toDateString()
                && $ligne->id > $ecriture->id)
            ->values();
    }

    /**
     * Ce qui a déjà été versé au titre d'une radiation.
     *
     * Une radiation ordinaire est payée depuis l'écran des versements, qui rattache
     * l'écriture à la radiation elle-même. Une succession, non : le règlement passe
     * par PaiementSuccessionCreate, qui vide le compte du défunt d'un seul geste et
     * rattache l'écriture au défunt (`succession_deces`). Chercher uniquement des
     * paiements `radiations` faisait donc toujours conclure « Non payé » sur une
     * succession pourtant réglée.
     *
     * Ce versement unique solde aussi la trésorerie qui dormait sur le compte avant
     * la liquidation : on ne retient donc que ce qui est imputable à la radiation,
     * plafonné à son montant, plutôt que la somme brute qui la dépasserait.
     */
    private function montantVerse(Radiation $radiation): float
    {
        $requete = EcritureCompteFinancier::where('type_ecriture', 'paiement');

        if ($radiation->estUneSuccession()) {
            $requete->where('compte_id', $radiation->compte_id)
                ->where('reference_type', 'succession_deces')
                ->whereDate('date_ecriture', '>=', $radiation->date_radiation);
        } else {
            $requete->where('reference_type', 'radiations')
                ->where('reference_id', $radiation->id);
        }

        $verse = abs((float) $requete->sum('montant'));

        return $radiation->estUneSuccession()
            ? min($verse, (float) $radiation->montant_total)
            : $verse;
    }

    public function paiement(EcritureCompteFinancier $ecriture)
    {
        abort_unless($ecriture->type_ecriture === 'paiement', 404);

        $ecriture->load('compte.investisseur');
        $this->verifierAcces($ecriture->compte);

        $historique = $this->historiqueDesVersements($ecriture);

        $pdf = Pdf::loadView('pdf.attestation-paiement', [
            'ecriture' => $ecriture,
            'compte' => $ecriture->compte,
            'investisseur' => $ecriture->compte->investisseur,
            'historique' => $historique,
            'totalVerse' => (float) $historique->sum(fn (EcritureCompteFinancier $ligne) => abs((float) $ligne->montant)),
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->rendrePdf($pdf, 'Attestation_Versement_' . $ecriture->id . '_' . $ecriture->date_ecriture->format('Y-m-d') . '.pdf');
    }
}
