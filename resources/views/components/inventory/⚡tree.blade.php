<?php

use App\Models\House;
use App\Models\Item;
use App\Services\ConflitService;
use App\Services\ImageService;
use App\Services\ItemService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** Terme de recherche live. */
    public string $recherche = '';

    /** Mode sélection multiple actif. */
    public bool $modeSelection = false;

    /** Ids des items cochés (en mode sélection). */
    public array $selection = [];

    /** N'afficher que les objets en conflit (liste à plat). */
    public bool $filtreConflits = false;

    /** Active/désactive le mode sélection (vide la sélection en sortant). */
    public function basculerSelection(): void
    {
        $this->modeSelection = !$this->modeSelection;
        if (!$this->modeSelection) {
            $this->selection = [];
        }
    }

    /** Vide la sélection sans quitter le mode. */
    public function toutDeselectionner(): void
    {
        $this->selection = [];
    }

    /** Maisons avec leurs items racines (et tout le sous-arbre + tags). */
    #[Computed]
    public function maisons()
    {
        return House::with([
                'rootItems' => fn ($q) => $q->orderBy('is_container')->orderBy('name'),
                'rootItems.tags',
                'rootItems.descendants',
            ])
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

    /** Objets en conflit, à plat (pour le bandeau et le filtre). */
    #[Computed]
    public function conflits()
    {
        return Item::where('en_conflit', true)->with('tags')->orderBy('name')->get();
    }

    /** Nombre de conflits à résoudre. */
    #[Computed]
    public function nbConflits(): int
    {
        return Item::where('en_conflit', true)->count();
    }

    /** Bascule l'affichage filtré sur les seuls conflits. */
    public function basculerFiltreConflits(): void
    {
        $this->filtreConflits = !$this->filtreConflits;
    }

    /** « Garder cette version » : le doublon gagne, l'original est supprimé. */
    public function garderConflit(int $itemId): void
    {
        $item = Item::find($itemId);
        if ($item) {
            (new ConflitService())->garder($item);
        }
        $this->apresResolution();
    }

    /** « Garder les deux » : lève le marquage, conserve les deux objets. */
    public function accepterConflit(int $itemId): void
    {
        $item = Item::find($itemId);
        if ($item) {
            (new ConflitService())->accepter($item);
        }
        $this->apresResolution();
    }

    /** Invalide les computed et sort du filtre s'il ne reste plus de conflit. */
    private function apresResolution(): void
    {
        unset($this->maisons, $this->resultats, $this->conflits, $this->nbConflits);
        if ($this->nbConflits === 0) {
            $this->filtreConflits = false;
        }
    }

    /** Supprime un item et son sous-arbre (CASCADE). */
    public function supprimer(int $itemId): void
    {
        $item = Item::find($itemId);
        if ($item) {
            $this->nettoyerImages((new ItemService())->idsDescendants($item));
            Item::whereKey($itemId)->delete();
        }
        unset($this->maisons, $this->resultats);
    }

    /** Supprime une maison et tout son contenu (CASCADE). */
    public function supprimerMaison(int $houseId): void
    {
        $this->nettoyerImages(Item::where('house_id', $houseId)->pluck('id')->all());
        House::whereKey($houseId)->delete();
        unset($this->maisons, $this->resultats);
    }

    /**
     * Supprime du disque les fichiers image des items donnés.
     * La cascade SQL ne déclenche pas l'event `deleting` du modèle, donc on
     * nettoie explicitement avant la suppression en masse.
     *
     * @param  array<int>  $itemIds
     */
    private function nettoyerImages(array $itemIds): void
    {
        if (empty($itemIds)) {
            return;
        }
        $service = new ImageService();
        Item::whereIn('id', $itemIds)
            ->whereNotNull('image_filename')
            ->pluck('image_filename')
            ->each(fn ($f) => $service->supprimer($f));
    }

    /** Rafraîchit l'arbre après une création/édition dans la modale. */
    #[On('arbre-modifie')]
    public function rafraichir(): void
    {
        unset($this->maisons, $this->resultats, $this->conflits, $this->nbConflits);
    }

    /** Tags par lot appliqués : on vide la sélection et on sort du mode. */
    #[On('tags-par-lot-applique')]
    public function apresTagsParLot(): void
    {
        $this->selection = [];
        $this->modeSelection = false;
        unset($this->maisons, $this->resultats);
    }

    /**
     * Déplace un item (drag & drop) vers une cible "house:ID" ou "item:ID".
     * Ignore silencieusement les déplacements invalides (anti-cycle, cible
     * non-conteneur, drop sur soi-même).
     */
    public function deplacerVers(int $itemId, string $cible): void
    {
        $item = Item::find($itemId);
        if (!$item) {
            return;
        }
        [$type, $cibleId] = array_pad(explode(':', $cible), 2, null);
        $cibleId = (int) $cibleId;
        $service = new ItemService();

        if ($type === 'house') {
            $service->deplacerVersMaison($item, $cibleId, null);
        } elseif ($type === 'item') {
            $dest = Item::find($cibleId);
            // garde-fous : cible existante, conteneur, pas dans le sous-arbre, pas le parent actuel
            if (!$dest || !$dest->is_container
                || $service->estDescendant($item, $dest)
                || $item->parent_id === $dest->id) {
                return;
            }
            if ($dest->house_id === $item->house_id) {
                $service->deplacer($item, $dest->id);
            } else {
                $service->deplacerVersMaison($item, $dest->house_id, $dest->id);
            }
        }

        unset($this->maisons, $this->resultats);
    }
};
?>

<div x-data="{ draggedId: null }">
    <header class="app-bar">
        <h1>Trouve — Inventaire</h1>
        <button type="button"
                wire:click="basculerSelection"
                style="margin-left:auto;border:1px solid #fff;border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.9rem;{{ $modeSelection ? 'background:#fff;color:#3584e4;' : 'background:transparent;color:#fff;' }}">{{ $modeSelection ? '✓ Sélection' : '☑️ Sélectionner' }}</button>
        <button type="button"
                wire:click="$dispatch('tags-gerer')"
                style="border:1px solid #fff;background:transparent;color:#fff;border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.9rem;">🏷️ Gérer les tags</button>
        <button type="button"
                wire:click="$dispatch('maison-creer')"
                style="border:1px solid #fff;background:transparent;color:#fff;border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.9rem;">+ Nouvelle maison</button>
        <span style="border-left:1px solid rgba(255,255,255,.4);padding-left:.6rem;margin-left:.3rem;display:flex;align-items:center;gap:.5rem;">
            <span style="font-size:.85rem;opacity:.9;">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit"
                        style="border:1px solid #fff;background:transparent;color:#fff;border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.9rem;">Déconnexion</button>
            </form>
        </span>
    </header>
    <main>
    <input
        type="search"
        wire:model.live.debounce.300ms="recherche"
        placeholder="Rechercher un objet, un tag…"
        style="width:100%;padding:.5rem .75rem;border:1px solid #c0bfbc;border-radius:6px;margin-bottom:1rem;font-size:1rem;"
    >

    {{-- Bandeau de conflits à résoudre --}}
    @if ($this->nbConflits > 0)
        <div style="display:flex;align-items:center;gap:.75rem;background:#fff4e5;border:1px solid #f0a30a;border-radius:6px;padding:.5rem .75rem;margin-bottom:1rem;">
            <span style="font-weight:600;color:#8a5a00;">⚠️ {{ $this->nbConflits }} conflit(s) à résoudre</span>
            <button type="button" wire:click="basculerFiltreConflits"
                    style="margin-left:auto;border:1px solid #f0a30a;background:{{ $filtreConflits ? '#f0a30a' : '#fff' }};color:{{ $filtreConflits ? '#fff' : '#8a5a00' }};border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.85rem;">
                {{ $filtreConflits ? '↩ Voir tout l\'inventaire' : '🔎 Ne voir que les conflits' }}
            </button>
        </div>
    @endif

    @if ($filtreConflits)
        {{-- Liste à plat des objets en conflit --}}
        <ul style="list-style:none;padding:0;margin:0;">
            @forelse ($this->conflits as $item)
                <li wire:key="conflit-{{ $item->id }}"
                    style="padding:.5rem .6rem;background:#fff;border:1px solid #f0a30a;border-left:4px solid #f0a30a;border-radius:6px;margin-bottom:.4rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                    <span>{{ $item->is_container ? '📦' : '•' }}</span>
                    @if ($item->image_filename)
                        <img src="{{ $item->image_url }}" alt=""
                             style="width:28px;height:28px;object-fit:cover;border-radius:4px;border:1px solid #e0e0e0;flex:none;">
                    @endif
                    <span style="font-weight:600;">{{ $item->name }}</span>
                    @foreach ($item->tags as $tag)
                        <span style="background:#e8f0fe;color:#1a73e8;border-radius:10px;padding:0 .5rem;font-size:.75rem;">{{ $tag->name }}</span>
                    @endforeach
                    <span style="margin-left:auto;display:flex;gap:.3rem;">
                        <button type="button" title="Modifier (fusionner à la main)"
                                wire:click="$dispatch('item-editer', { itemId: {{ $item->id }} })"
                                style="border:1px solid #c0bfbc;background:#fff;border-radius:6px;padding:.2rem .5rem;cursor:pointer;font-size:.8rem;">✏️ Fusionner</button>
                        <button type="button" title="Garder les deux objets"
                                wire:click="accepterConflit({{ $item->id }})"
                                style="border:1px solid #3584e4;background:#fff;color:#3584e4;border-radius:6px;padding:.2rem .5rem;cursor:pointer;font-size:.8rem;">⇄ Garder les deux</button>
                        <button type="button" title="Garder cette version, supprimer l'autre"
                                wire:click="garderConflit({{ $item->id }})"
                                wire:confirm="Garder « {{ $item->name }} » et supprimer l'autre version ?"
                                style="border:1px solid #2e7d32;background:#2e7d32;color:#fff;border-radius:6px;padding:.2rem .5rem;cursor:pointer;font-size:.8rem;">✅ Garder cette version</button>
                    </span>
                </li>
            @empty
                <p style="color:#5e5c64;">Aucun conflit.</p>
            @endforelse
        </ul>
    @elseif (trim($recherche) === '')
        <div style="display:flex;gap:.5rem;margin-bottom:.75rem;">
            <button type="button" @click="$dispatch('expand-all')"
                    style="border:1px solid #c0bfbc;background:#fff;border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.8rem;color:#5e5c64;">▾ Tout déplier</button>
            <button type="button" @click="$dispatch('collapse-all')"
                    style="border:1px solid #c0bfbc;background:#fff;border-radius:6px;padding:.2rem .6rem;cursor:pointer;font-size:.8rem;color:#5e5c64;">▸ Tout replier</button>
        </div>
    @endif

    @unless ($filtreConflits)
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
            <section style="margin-bottom:1.5rem;"
                     x-data="{ survol: false }"
                     @dragover.prevent="survol = true"
                     @dragleave="survol = false"
                     @drop.prevent="survol = false; if (draggedId) $wire.deplacerVers(draggedId, 'house:{{ $maison->id }}')"
                     :style="survol ? 'margin-bottom:1.5rem;outline:2px dashed #3584e4;outline-offset:3px;border-radius:6px;' : 'margin-bottom:1.5rem;'">
                <h2 style="font-size:1rem;border-bottom:2px solid #3584e4;padding-bottom:.25rem;display:flex;align-items:center;gap:.5rem;">
                    🏠 {{ $maison->name }}
                    <span style="font-size:.7rem;color:#5e5c64;font-weight:400;">(déposer ici = racine)</span>
                    <span style="margin-left:auto;display:flex;gap:.3rem;align-items:center;">
                        <button type="button"
                                wire:click="$dispatch('item-creer', { houseId: {{ $maison->id }} })"
                                title="Ajouter un objet à la racine"
                                style="border:1px solid #3584e4;background:#fff;color:#3584e4;border-radius:6px;padding:0 .5rem;cursor:pointer;font-size:.9rem;">+ Ajouter</button>
                        <button type="button" title="Renommer la maison"
                                wire:click="$dispatch('maison-editer', { houseId: {{ $maison->id }} })"
                                style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">✏️</button>
                        <button type="button" title="Supprimer la maison"
                                wire:click="supprimerMaison({{ $maison->id }})"
                                wire:confirm="Supprimer la maison « {{ $maison->name }} » et TOUT son contenu ?"
                                style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">🗑️</button>
                    </span>
                </h2>
                <ul style="list-style:none;padding-left:1.25rem;margin:.15rem 0;border-left:1px dashed #d0d0d0;">
                    @foreach ($maison->rootItems as $item)
                        <x-inventory.item-node :item="$item" :mode-selection="$modeSelection" />
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
    @endunless
    </main>

    {{-- Barre d'action de la sélection multiple --}}
    @if ($modeSelection && count($selection) > 0)
        <div style="position:fixed;left:0;right:0;bottom:0;background:#3584e4;color:#fff;display:flex;align-items:center;gap:.75rem;padding:.6rem 1rem;box-shadow:0 -4px 16px rgba(0,0,0,.2);z-index:40;">
            <span style="font-weight:600;">{{ count($selection) }} objet(s) sélectionné(s)</span>
            <button type="button"
                    wire:click="$dispatch('tags-lot-ouvrir', { itemIds: {{ json_encode(array_map('intval', $selection)) }} })"
                    style="margin-left:auto;border:none;background:#fff;color:#3584e4;border-radius:6px;padding:.35rem .8rem;cursor:pointer;font-weight:600;">🏷️ Tags par lot</button>
            <button type="button" wire:click="toutDeselectionner"
                    style="border:1px solid #fff;background:transparent;color:#fff;border-radius:6px;padding:.35rem .8rem;cursor:pointer;">Tout décocher</button>
        </div>
    @endif

    <livewire:inventory.house-form />
    <livewire:inventory.item-form />
    <livewire:inventory.item-move />
    <livewire:inventory.item-tag-bulk />
    <livewire:inventory.tag-manager />
</div>
