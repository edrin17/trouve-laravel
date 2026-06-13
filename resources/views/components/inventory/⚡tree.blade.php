<?php

use App\Models\House;
use App\Models\Item;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** Terme de recherche live. */
    public string $recherche = '';

    /** Maisons avec leurs items racines (et tout le sous-arbre + tags). */
    #[Computed]
    public function maisons()
    {
        return House::with(['rootItems' => fn ($q) => $q->orderBy('name'), 'rootItems.tags', 'rootItems.descendants'])
            ->orderBy('name')
            ->get();
    }

    /** Résultats de recherche à plat (toutes maisons), si recherche active. */
    #[Computed]
    public function resultats()
    {
        $terme = trim($this->recherche);
        if ($terme === '') {
            return collect();
        }
        return Item::search($terme)->with('tags')->orderBy('name')->get();
    }

    /** Supprime un item et son sous-arbre (CASCADE). */
    public function supprimer(int $itemId): void
    {
        Item::whereKey($itemId)->delete();
        unset($this->maisons, $this->resultats);
    }

    /** Rafraîchit l'arbre après une création/édition dans la modale. */
    #[On('arbre-modifie')]
    public function rafraichir(): void
    {
        unset($this->maisons, $this->resultats);
    }
};
?>

<div>
    <header class="app-bar">
        <h1>Trouve — Inventaire</h1>
    </header>
    <main>
    <input
        type="search"
        wire:model.live.debounce.300ms="recherche"
        placeholder="Rechercher un objet, un tag…"
        style="width:100%;padding:.5rem .75rem;border:1px solid #c0bfbc;border-radius:6px;margin-bottom:1rem;font-size:1rem;"
    >

    @if (trim($recherche) !== '')
        <p style="color:#5e5c64;font-size:.9rem;">{{ $this->resultats->count() }} résultat(s)</p>
        <ul style="list-style:none;padding:0;margin:0;">
            @foreach ($this->resultats as $item)
                <li style="padding:.4rem .6rem;background:#fff;border:1px solid #e0e0e0;border-radius:6px;margin-bottom:.3rem;">
                    {{ $item->name }}
                    @foreach ($item->tags as $tag)
                        <span style="background:#e8f0fe;color:#1a73e8;border-radius:10px;padding:0 .5rem;font-size:.75rem;margin-left:.3rem;">{{ $tag->name }}</span>
                    @endforeach
                </li>
            @endforeach
        </ul>
    @else
        @foreach ($this->maisons as $maison)
            <section style="margin-bottom:1.5rem;">
                <h2 style="font-size:1rem;border-bottom:2px solid #3584e4;padding-bottom:.25rem;display:flex;align-items:center;gap:.5rem;">
                    🏠 {{ $maison->name }}
                    <button type="button"
                            wire:click="$dispatch('item-creer', { houseId: {{ $maison->id }} })"
                            title="Ajouter un objet à la racine"
                            style="margin-left:auto;border:1px solid #3584e4;background:#fff;color:#3584e4;border-radius:6px;padding:0 .5rem;cursor:pointer;font-size:.9rem;">+ Ajouter</button>
                </h2>
                <ul style="list-style:none;padding-left:0;margin:0;">
                    @foreach ($maison->rootItems as $item)
                        <x-inventory.item-node :item="$item" />
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
    </main>

    <livewire:inventory.item-form />
</div>
