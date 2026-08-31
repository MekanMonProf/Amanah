<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class JournalAudit extends Component
{
    use WithPagination;

    public string $recherche = '';
    public string $filtreAction = '';
    public string $filtreEntite = '';
    public string $dateDebut = '';
    public string $dateFin = '';

    public array $lignesOuvertes = [];

    protected $paginationTheme = 'tailwind';

    public function basculerDetail(int $id): void
    {
        if (in_array($id, $this->lignesOuvertes, true)) {
            $this->lignesOuvertes = array_diff($this->lignesOuvertes, [$id]);
        } else {
            $this->lignesOuvertes[] = $id;
        }
    }

    public function updatingRecherche(): void { $this->resetPage(); }
    public function updatingFiltreAction(): void { $this->resetPage(); }
    public function updatingFiltreEntite(): void { $this->resetPage(); }
    public function updatingDateDebut(): void { $this->resetPage(); }
    public function updatingDateFin(): void { $this->resetPage(); }

    public function reinitialiserFiltres(): void
    {
        $this->reset(['recherche', 'filtreAction', 'filtreEntite', 'dateDebut', 'dateFin']);
        $this->resetPage();
    }

    protected function requeteFiltree()
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($this->recherche) {
            $query->where(function ($q) {
                $q->whereHas('user', fn ($u) => $u->where('nom', 'like', "%{$this->recherche}%")->orWhere('email', 'like', "%{$this->recherche}%"))
                  ->orWhere('entite_id', $this->recherche);
            });
        }

        if ($this->filtreAction) {
            $query->where('action', $this->filtreAction);
        }

        if ($this->filtreEntite) {
            $query->where('entite', $this->filtreEntite);
        }

        if ($this->dateDebut) {
            $query->whereDate('created_at', '>=', $this->dateDebut);
        }

        if ($this->dateFin) {
            $query->whereDate('created_at', '<=', $this->dateFin);
        }

        return $query;
    }

    public function exporterCsv()
    {
        $entrees = $this->requeteFiltree()->get();

        return response()->streamDownload(function () use ($entrees) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, "\xEF\xBB\xBF");

            fputcsv($flux, ['Date', 'Utilisateur', 'Email', 'Action', 'Entité', 'ID entité', 'Avant', 'Après', 'IP'], ';');

            foreach ($entrees as $e) {
                fputcsv($flux, [
                    $e->created_at->format('d/m/Y H:i'),
                    trim(($e->user?->nom ?? '') . ' ' . ($e->user?->prenom ?? '')),
                    $e->user?->email,
                    $e->action,
                    $e->entite,
                    $e->entite_id,
                    $e->donnees_avant ? json_encode($e->donnees_avant, JSON_UNESCAPED_UNICODE) : '',
                    $e->donnees_apres ? json_encode($e->donnees_apres, JSON_UNESCAPED_UNICODE) : '',
                    $e->ip_address,
                ], ';');
            }

            fclose($flux);
        }, 'journal_audit_' . now()->format('Y-m-d') . '.csv');
    }

    public function exporterPdf()
    {
        $entrees = $this->requeteFiltree()->get();

        $filtresActifs = collect([
            $this->recherche ? "recherche « {$this->recherche} »" : null,
            $this->filtreAction ? "action {$this->filtreAction}" : null,
            $this->filtreEntite ? "entité {$this->filtreEntite}" : null,
        ])->filter()->implode(', ');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.journal-audit', [
            'entrees' => $entrees,
            'filtresActifs' => $filtresActifs,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Journal_Audit_' . now()->format('Y-m-d') . '.pdf');
    }

    public function render()
    {
        return view('livewire.journal-audit', [
            'entrees' => $this->requeteFiltree()->paginate(25),
            'actionsDisponibles' => AuditLog::query()->distinct()->pluck('action'),
            'entitesDisponibles' => AuditLog::query()->distinct()->pluck('entite'),
        ]);
    }
}
