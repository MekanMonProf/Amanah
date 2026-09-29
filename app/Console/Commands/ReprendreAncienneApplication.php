<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\User;
use App\Support\AncienneBase;
use App\Support\Telephone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reprend les comptes de l'application qui précédait AMANAH.
 *
 * L'ancienne application distribuait des relevés : elle tenait 418 comptes et
 * 4 207 fichiers PDF, mais aucune donnée financière — ses tables souscriptions
 * et versements sont vides. Il n'y a donc ni actions, ni soldes, ni paiements à
 * reprendre : uniquement des personnes, leurs pièces d'identité et leur
 * rattachement à une antenne.
 *
 * Trois natures de compte, selon le rôle :
 *   ADMIN  →  un utilisateur administrateur
 *   S      →  cinq secrétaires, une par antenne, qui deviennent gestionnaires
 *   A      →  les actionnaires, qui deviennent investisseurs
 *
 * Deux principes gouvernent le reste.
 *
 * Les mots de passe sont recopiés tels quels. Ils sont en bcrypt, que Laravel
 * vérifie nativement : personne n'a à en changer, et aucun mot de passe
 * temporaire n'est à faire circuler pour 418 personnes.
 *
 * Aucun compte d'investissement n'est ouvert. Un compte sans action ni solde
 * serait un compte ouvert resté sans suite — le contrôle de cohérence le
 * signalerait, à raison. Les comptes s'ouvriront à la première souscription.
 *
 * La commande se rejoue sans dommage : un investisseur déjà repris est reconnu
 * à son identifiant et laissé intact.
 */
class ReprendreAncienneApplication extends Command
{
    protected $signature = 'amanah:reprendre-ancienne-application
                            {--simulation : Montre ce qui serait fait, sans rien écrire}
                            {--sans-acces : Ne crée pas les accès au portail des investisseurs}';

    protected $description = "Reprend les comptes de l'ancienne application dans AMANAH";

    private string $connexion;

    /** @var array<string, int> antenne_id de l'ancienne base => gestionnaires.id */
    private array $gestionnaires = [];

    /** @var list<array{identifiant: string, motif: string}> */
    private array $signalements = [];

    public function handle(): int
    {
        $manquantes = [];
        $connexion = AncienneBase::connexion($manquantes);

        if ($connexion === null) {
            $this->error('Ancienne application introuvable.');
            $this->line('Cherchee dans la base « ' . AncienneBase::base(AncienneBase::connexionEssayee()) . ' ».');
            $this->line('Tables attendues et absentes : ' . implode(', ', $manquantes) . '.');
            $this->line('Si elle vit dans une autre base, renseignez ANCIENNE_DB_DATABASE.');

            return self::FAILURE;
        }

        $this->connexion = $connexion;
        $simulation = (bool) $this->option('simulation');

        $this->info($simulation
            ? 'SIMULATION — rien ne sera écrit.'
            : 'Reprise depuis « ' . AncienneBase::base($connexion) . ' ».');
        $this->newLine();

        $comptes = $this->comptesParRole();

        $this->line(sprintf(
            '  %d administrateur(s), %d secrétaire(s), %d actionnaire(s).',
            count($comptes[AncienneBase::ROLE_ADMIN] ?? []),
            count($comptes[AncienneBase::ROLE_SECRETAIRE] ?? []),
            count($comptes[AncienneBase::ROLE_ACTIONNAIRE] ?? []),
        ));

        // Tout ou rien : une reprise interrompue en chemin laisserait des
        // investisseurs sans gestionnaire et des gestionnaires sans portefeuille.
        DB::beginTransaction();

        try {
            $bilan = [
                'administrateurs' => $this->reprendreEncadrement($comptes[AncienneBase::ROLE_ADMIN] ?? [], 'administrateur'),
                'gestionnaires' => $this->reprendreEncadrement($comptes[AncienneBase::ROLE_SECRETAIRE] ?? [], 'gestionnaire'),
                'investisseurs' => $this->reprendreInvestisseurs($comptes[AncienneBase::ROLE_ACTIONNAIRE] ?? []),
            ];

            // Une reprise de plusieurs centaines de dossiers ne doit pas être la
            // seule opération de la plateforme à ne laisser aucune trace. Elle
            // est lancée en ligne de commande, donc sans utilisateur connecté :
            // audit_logs.user_id reste vide, ce que la colonne autorise.
            if (! $simulation) {
                AuditLog::enregistrer(
                    action: 'reprise_ancienne_application',
                    entite: 'investisseur',
                    apres: [
                        'source' => AncienneBase::base($connexion),
                        'administrateurs' => $bilan['administrateurs']['crees'],
                        'gestionnaires' => $bilan['gestionnaires']['crees'],
                        'investisseurs' => $bilan['investisseurs']['crees'],
                        'acces_portail' => $bilan['investisseurs']['acces'],
                        'a_arbitrer' => count($this->signalements),
                    ],
                );
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("Reprise interrompue, rien n'a été écrit : " . $e->getMessage());

            return self::FAILURE;
        }

        $simulation ? DB::rollBack() : DB::commit();

        $this->bilan($bilan, $simulation);

        return self::SUCCESS;
    }

    /** @return array<string, list<object>> code de rôle => comptes */
    private function comptesParRole(): array
    {
        $roles = DB::connection($this->connexion)->table('roles')->pluck('code_role', 'id');

        return DB::connection($this->connexion)->table('utilisateurs')
            ->orderBy('id')->get()
            ->groupBy(fn ($u) => $roles[$u->role_id] ?? 'inconnu')
            ->map(fn ($groupe) => $groupe->all())
            ->all();
    }

    /**
     * Administrateur et secrétaires : des utilisateurs d'AMANAH.
     *
     * Une secrétaire devient gestionnaire et garde son antenne comme
     * portefeuille — l'ancienne application rattachait déjà chaque actionnaire à
     * une antenne, et il y a exactement une secrétaire par antenne.
     *
     * @param  list<object>  $comptes
     * @return array{crees: int, existants: int}
     */
    private function reprendreEncadrement(array $comptes, string $role): array
    {
        $bilan = ['crees' => 0, 'existants' => 0];

        foreach ($comptes as $ancien) {
            $existant = $this->encadrementExistant($ancien, $role);

            if ($existant !== null) {
                $bilan['existants']++;
                $this->rattacherGestionnaire($role, $ancien, $existant);

                continue;
            }

            $telephone = $this->telephoneLibre($ancien);

            if ($telephone === null && blank($ancien->email)) {
                $this->signalements[] = [
                    'nature' => 'encadrement',
                    'identifiant' => $ancien->identifiant,
                    'motif' => blank($ancien->telephone)
                        ? 'aucun téléphone ni email — ne pourra pas se connecter'
                        : 'téléphone partagé avec un autre compte — ne pourra pas se connecter',
                ];
            }

            $user = User::create([
                'nom' => $ancien->nom,
                'prenom' => $ancien->prenom ?: '',
                'email' => $ancien->email ?: null,
                'telephone' => $telephone,
                // Recopié tel quel : bcrypt, que Laravel vérifie nativement.
                'password' => $ancien->mot_de_passe,
                'role' => $role,
                'langue' => 'fr',
                'actif' => (bool) $ancien->actif,
                'doit_changer_mot_de_passe' => (bool) $ancien->doit_changer_mdp,
            ]);

            $bilan['crees']++;
            $this->rattacherGestionnaire($role, $ancien, $user);
        }

        return $bilan;
    }

    private function rattacherGestionnaire(string $role, object $ancien, User $user): void
    {
        if ($role !== 'gestionnaire') {
            return;
        }

        $gestionnaire = Gestionnaire::firstOrCreate(
            ['user_id' => $user->id],
            ['actif' => (bool) $ancien->actif],
        );

        if ($ancien->antenne_id !== null) {
            $this->gestionnaires[(string) $ancien->antenne_id] = $gestionnaire->id;
        }
    }

    /**
     * @param  list<object>  $comptes
     * @return array{crees: int, existants: int, acces: int, sans_acces: int}
     */
    private function reprendreInvestisseurs(array $comptes): array
    {
        $bilan = ['crees' => 0, 'existants' => 0, 'acces' => 0, 'sans_acces' => 0];
        $avecAcces = ! $this->option('sans-acces');

        foreach ($comptes as $ancien) {
            if (Investisseur::where('identifiant_externe', $ancien->identifiant)->exists()) {
                $bilan['existants']++;

                continue;
            }

            $telephone = Telephone::normaliser($ancien->telephone);

            $investisseur = Investisseur::create([
                'identifiant_externe' => $ancien->identifiant,
                'type_personne' => 'physique',
                'nom' => $ancien->nom,
                'prenom' => $ancien->prenom ?: null,
                // Le numéro brut est conservé quand il n'a pas pu être normalisé :
                // mieux vaut un numéro imparfait que plus aucun moyen de joindre
                // la personne.
                'telephone' => $telephone ?: ($ancien->telephone ?: null),
                'whatsapp' => $telephone ?: null,
                'email' => $ancien->email ?: null,
                'type_identification' => $this->typePiece($ancien->type_piece),
                'numero_identification' => $ancien->numero_piece ?: null,
                'date_expiration_piece' => $this->date($ancien->date_expiration),
                'pays' => $this->pays($ancien->pays),
                'langue' => 'fr',
                'notes_internes' => $ancien->observations ?: null,
                'gestionnaire_id' => $this->gestionnaires[(string) $ancien->antenne_id] ?? null,
                'statut' => $ancien->actif ? 'actif' : 'inactif',
                'created_at' => $ancien->date_creation ?: now(),
            ]);

            $bilan['crees']++;

            if (! $avecAcces) {
                continue;
            }

            $this->ouvrirAcces($investisseur, $ancien, $telephone)
                ? $bilan['acces']++
                : $bilan['sans_acces']++;
        }

        return $bilan;
    }

    /**
     * L'accès au portail, quand le téléphone peut servir d'identifiant.
     *
     * Aucun compte de l'ancienne application n'a d'email : le téléphone est le
     * seul identifiant possible, et users.telephone est unique. Douze numéros
     * sont partagés par deux personnes — des familles, vraisemblablement. Le
     * premier arrivé prend l'identifiant, les suivants sont signalés : cet
     * arbitrage revient au gestionnaire, pas à une commande.
     *
     * Le dossier est créé dans tous les cas. Ne pas pouvoir se connecter au
     * portail n'empêche ni de souscrire, ni d'être suivi.
     */
    private function ouvrirAcces(Investisseur $investisseur, object $ancien, ?string $telephone): bool
    {
        if ($telephone === null) {
            $this->signalements[] = [
                'nature' => 'investisseur',
                'identifiant' => $ancien->identifiant,
                'motif' => 'numéro absent ou illisible',
            ];

            return false;
        }

        if (User::where('telephone', $telephone)->exists()) {
            $this->signalements[] = [
                'nature' => 'investisseur',
                'identifiant' => $ancien->identifiant,
                'motif' => 'numéro déjà utilisé par un autre compte',
            ];

            return false;
        }

        $user = User::create([
            'nom' => $investisseur->nom,
            // users.prenom est NOT NULL, contrairement à investisseurs.prenom.
            'prenom' => $investisseur->prenom ?? '',
            'email' => null,
            'telephone' => $telephone,
            'password' => $ancien->mot_de_passe,
            'role' => 'investisseur',
            'langue' => 'fr',
            'actif' => (bool) $ancien->actif,
            'doit_changer_mot_de_passe' => (bool) $ancien->doit_changer_mdp,
        ]);

        $investisseur->update(['user_id' => $user->id]);

        return true;
    }

    /**
     * Reconnaître un membre de l'encadrement déjà repris, pour ne pas le créer
     * deux fois si la commande est relancée.
     *
     * Surtout pas par le téléphone. Quatre des cinq secrétaires partagent le
     * même numéro — celui du bureau, vraisemblablement — et s'en servir comme
     * clé revenait à prendre quatre personnes pour une seule : trois comptes
     * n'étaient pas créés, et quatre antennes se retrouvaient rattachées au
     * même gestionnaire. Un numéro partagé identifie un lieu, pas quelqu'un.
     *
     * L'email quand il existe, sinon le nom complet dans le même rôle. Aucun
     * des six comptes d'encadrement n'a d'email, donc c'est le nom qui sert ;
     * il est stable et ces comptes se comptent sur les doigts d'une main.
     */
    private function encadrementExistant(object $ancien, string $role): ?User
    {
        if (filled($ancien->email)) {
            return User::where('email', $ancien->email)->first();
        }

        return User::where('role', $role)
            ->where('nom', $ancien->nom)
            ->where('prenom', $ancien->prenom ?: '')
            ->first();
    }

    /**
     * Le téléphone, seulement s'il peut encore servir d'identifiant unique.
     *
     * users.telephone est unique : le premier arrivé le prend, et les suivants
     * sont créés sans. Le compte existe quand même — il lui manque seulement de
     * quoi se connecter, ce que le bilan signale.
     */
    private function telephoneLibre(object $ancien): ?string
    {
        $telephone = Telephone::normaliser($ancien->telephone);

        if ($telephone === null || User::where('telephone', $telephone)->exists()) {
            return null;
        }

        return $telephone;
    }

    /**
     * Le type de pièce, ramené à quatre libellés.
     *
     * Douze graphies pour quatre pièces réelles, dont 113 où les accents avaient
     * été remplacés par des points d'interrogation avant même l'export. On ne
     * compare donc pas des chaînes exactes : on cherche le mot qui distingue,
     * une fois la ponctuation et la casse écartées.
     */
    private function typePiece(?string $valeur): ?string
    {
        $reduit = preg_replace('/[^a-z]+/', ' ', strtolower((string) $valeur)) ?? '';

        return match (true) {
            str_contains($reduit, 'cedeao') => "Carte d'identité CEDEAO",
            str_contains($reduit, 'passeport') => 'Passeport',
            str_contains($reduit, 'permis') => 'Permis de conduire',
            str_contains($reduit, 'national') => "Carte d'identité nationale",
            default => null,
        };
    }

    /**
     * Le pays, dont le Sénégal s'écrivait de quatre façons.
     *
     * « Senegal », « SENEGAL », « Sénégal » et « S?n?gal » désignent le même
     * pays. Les accents perdus ne laissent que les consonnes pour le
     * reconnaître, d'où la comparaison sur la forme et non sur le texte.
     */
    private function pays(?string $valeur): ?string
    {
        $brut = trim((string) $valeur);
        $reduit = preg_replace('/[^a-z]+/', '', strtolower($brut)) ?? '';

        if ($reduit === '') {
            return null;
        }

        if (preg_match('/^s.n.gal$/', strtolower($brut)) === 1 || $reduit === 'senegal' || $reduit === 'sngal') {
            return 'Sénégal';
        }

        return ucfirst($brut);
    }

    /** Une date que MySQL écrivait 0000-00-00 quand elle manquait. */
    private function date(?string $valeur): ?string
    {
        return ($valeur === null || $valeur === '' || str_starts_with($valeur, '0000'))
            ? null
            : $valeur;
    }

    private function bilan(array $bilan, bool $simulation): void
    {
        $this->newLine();
        $this->table(['', 'Créés', 'Déjà présents'], [
            ['Administrateurs', $bilan['administrateurs']['crees'], $bilan['administrateurs']['existants']],
            ['Gestionnaires', $bilan['gestionnaires']['crees'], $bilan['gestionnaires']['existants']],
            ['Investisseurs', $bilan['investisseurs']['crees'], $bilan['investisseurs']['existants']],
        ]);

        if (! $this->option('sans-acces')) {
            $this->line(sprintf(
                '  Accès au portail : %d ouvert(s), %d à arbitrer.',
                $bilan['investisseurs']['acces'], $bilan['investisseurs']['sans_acces'],
            ));
        }

        $encadrement = array_filter($this->signalements, fn ($s) => $s['nature'] === 'encadrement');
        $investisseurs = array_filter($this->signalements, fn ($s) => $s['nature'] === 'investisseur');

        if ($encadrement !== []) {
            $this->newLine();
            $this->warn('  À régler avant la mise en service — comptes sans moyen de connexion :');
            foreach ($encadrement as $s) {
                $this->line(sprintf('    %-12s %s', $s['identifiant'], $s['motif']));
            }
            $this->line('    Donnez-leur un email ou un téléphone propre depuis Paramétrage.');
        }

        if ($investisseurs !== []) {
            $this->newLine();
            $this->warn('  À arbitrer — dossier créé, mais sans accès au portail :');
            foreach ($investisseurs as $s) {
                $this->line(sprintf('    %-12s %s', $s['identifiant'], $s['motif']));
            }
        }

        $this->newLine();
        $this->line($simulation
            ? "  SIMULATION — la base n'a pas été modifiée. Relancez sans --simulation pour écrire."
            : "  Les mots de passe ont été repris tels quels : personne n'a à en changer.");
    }
}
