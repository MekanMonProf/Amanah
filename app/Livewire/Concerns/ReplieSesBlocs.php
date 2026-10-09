<?php

namespace App\Livewire\Concerns;

/**
 * Les blocs d'un écran qu'on peut replier, et la mémoire de ce choix.
 *
 * Replié, un bloc n'est pas caché : il n'est plus rendu du tout, et ni sa
 * requête ni ses composants imbriqués ne sont construits. C'est pourquoi la
 * décision se prend côté serveur, et non dans le navigateur.
 *
 * Le défaut d'un bloc ne se déclare qu'ici, par repliesParDefaut(). Chaque
 * écran le lisait auparavant de son côté : la vue affichait un bloc replié
 * pendant que la bascule le supposait ouvert, et le premier clic ne faisait
 * que confirmer un état déjà vrai — il en fallait deux pour ouvrir.
 */
trait ReplieSesBlocs
{
    /** @var array<string, bool> */
    public array $blocsReplies = [];

    /** À appeler depuis mount() : le choix se retient d'un dossier à l'autre. */
    protected function reprendreLesBlocsReplies(): void
    {
        $this->blocsReplies = session($this->cleDeSessionDesBlocs(), []);
    }

    public function basculerBloc(string $cle): void
    {
        $this->blocsReplies[$cle] = ! $this->estReplie($cle);

        session()->put($this->cleDeSessionDesBlocs(), $this->blocsReplies);
    }

    /** L'état d'un bloc : celui qu'on lui a donné, sinon son défaut. */
    public function estReplie(string $cle): bool
    {
        return $this->blocsReplies[$cle] ?? ($this->repliesParDefaut()[$cle] ?? false);
    }

    /**
     * Les écrans ont chacun leur clé : replier le portefeuille d'un
     * gestionnaire ne doit pas replier les achats d'un investisseur.
     */
    abstract protected function cleDeSessionDesBlocs(): string;

    /**
     * Les blocs repliés tant que personne ne les a ouverts. Les autres
     * s'ouvrent : on vient les lire.
     *
     * @return array<string, bool>
     */
    protected function repliesParDefaut(): array
    {
        return [];
    }
}
