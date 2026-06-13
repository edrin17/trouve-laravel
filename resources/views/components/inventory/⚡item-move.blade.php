<?php

use App\Models\House;
use App\Models\Item;
use App\Services\ItemService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $ouvert = false;
    public ?int $itemId = null;
    public string $itemNom = '';

    /** Destination choisie : "house:ID" (racine) ou "item:ID" (dans un conteneur). */
    public string $destination = '';

    #[On('item-deplacer')]
    public function ouvrir(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $this->itemId = $item->id;
        $this->itemNom = $item->name;
        $this->destination = '';
        $this->ouvert = true;
    }

    /**
     * Destinations possibles : racine de chaque maison + chaque conteneur,
     * en excluant l'item déplacé et tout son sous-arbre (anti-cycle).
     *
     * @return array<array{value:string,label:string}>
     */
    #[Computed]
    public function destinations(): array
    {
        if (!$this->itemId) {
            return [];
        }
        $item = Item::find($this->itemId);
        $service = new ItemService();
        $exclus = $service->idsDescendants($item); // item + descendants

        $options = [];
        $maisons = House::with(['rootItems' => fn ($q) => $q->orderBy('name'), 'rootItems.descendants'])
            ->orderBy('name')->get();

        foreach ($maisons as $maison) {
            $options[] = ['value' => "house:{$maison->id}", 'label' => "🏠 {$maison->name} (racine)"];

            $walk = function ($noeud, $prof) use (&$walk, &$options, $exclus) {
                if (in_array($noeud->id, $exclus, true) || !$noeud->is_container) {
                    // on n'entre pas dans le sous-arbre exclu, ni sous un non-conteneur
                    if (in_array($noeud->id, $exclus, true)) {
                        return;
                    }
                }
                if ($noeud->is_container && !in_array($noeud->id, $exclus, true)) {
                    $options[] = [
                        'value' => "item:{$noeud->id}",
                        'label' => str_repeat('   ', $prof) . '📦 ' . $noeud->name,
                    ];
                }
                foreach ($noeud->descendants->sortBy('name') as $enfant) {
                    if (!in_array($enfant->id, $exclus, true)) {
                        $walk($enfant, $prof + 1);
                    }
                }
            };
            foreach ($maison->rootItems->sortBy('name') as $racine) {
                if (!in_array($racine->id, $exclus, true)) {
                    $walk($racine, 1);
                }
            }
        }

        return $options;
    }

    public function deplacer(): void
    {
        $this->validate(['destination' => 'required']);

        $item = Item::findOrFail($this->itemId);
        $service = new ItemService();

        [$type, $cibleId] = explode(':', $this->destination);
        $cibleId = (int) $cibleId;

        if ($type === 'house') {
            // racine d'une maison
            $service->deplacerVersMaison($item, $cibleId, null);
        } else {
            // sous un conteneur : récupérer sa maison
            $cible = Item::findOrFail($cibleId);
            if ($cible->house_id === $item->house_id) {
                $service->deplacer($item, $cible->id);
            } else {
                $service->deplacerVersMaison($item, $cible->house_id, $cible->id);
            }
        }

        $this->ouvert = false;
        $this->dispatch('arbre-modifie');
    }

    public function fermer(): void
    {
        $this->ouvert = false;
        $this->resetValidation();
    }
};
?>

<div>
    @if ($ouvert)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:50;"
             wire:click.self="fermer">
            <div style="background:#fff;border-radius:10px;padding:1.25rem;width:min(460px,92vw);box-shadow:0 10px 40px rgba(0,0,0,.25);">
                <h2 style="margin-top:0;font-size:1.1rem;">Déplacer « {{ $itemNom }} »</h2>

                <form wire:submit="deplacer" style="display:flex;flex-direction:column;gap:.75rem;">
                    <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                        Nouvelle destination
                        <select wire:model="destination"
                                style="padding:.45rem;border:1px solid #c0bfbc;border-radius:6px;font-size:1rem;">
                            <option value="">— choisir —</option>
                            @foreach ($this->destinations as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @error('destination') <span style="color:#c01c28;font-size:.8rem;">Choisissez une destination.</span> @enderror
                    </label>

                    <div style="display:flex;justify-content:flex-end;gap:.5rem;">
                        <button type="button" wire:click="fermer"
                                style="padding:.45rem .9rem;border:1px solid #c0bfbc;background:#fff;border-radius:6px;cursor:pointer;">Annuler</button>
                        <button type="submit"
                                style="padding:.45rem .9rem;border:none;background:#3584e4;color:#fff;border-radius:6px;cursor:pointer;">Déplacer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
