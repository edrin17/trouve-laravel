<?php

use App\Models\Item;
use App\Models\Tag;
use App\Services\TagService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $ouvert = false;

    /** Items concernés par l'édition de tags par lot. */
    public array $itemIds = [];

    /** Tags à ajouter : liste de noms (chips) + saisie courante. */
    public array $aAjouter = [];
    public string $tagSaisie = '';

    /** Tags à retirer : noms cochés parmi ceux présents sur la sélection. */
    public array $aRetirer = [];

    #[On('tags-lot-ouvrir')]
    public function ouvrir(array $itemIds): void
    {
        $this->reset(['aAjouter', 'tagSaisie', 'aRetirer']);
        $this->itemIds = array_values(array_map('intval', $itemIds));
        $this->ouvert = true;
    }

    /** Vocabulaire complet (pour les suggestions d'ajout). */
    #[Computed]
    public function vocabulaire(): array
    {
        return Tag::orderBy('name')->pluck('name')->all();
    }

    /**
     * Tags présents sur au moins un item de la sélection (candidats au retrait),
     * avec le nombre d'items concernés.
     *
     * @return array<array{name:string,count:int}>
     */
    #[Computed]
    public function tagsPresents(): array
    {
        if (empty($this->itemIds)) {
            return [];
        }

        return Tag::whereHas('items', fn ($q) => $q->whereIn('items.id', $this->itemIds))
            ->withCount(['items' => fn ($q) => $q->whereIn('items.id', $this->itemIds)])
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $t) => ['name' => $t->name, 'count' => $t->items_count])
            ->all();
    }

    /** Ajoute le(s) tag(s) saisi(s) à la liste (normalisé, sans doublon). */
    public function ajouterTag(): void
    {
        foreach (explode(';', $this->tagSaisie) as $brut) {
            $tag = trim(mb_strtolower($brut));
            if ($tag !== '' && !in_array($tag, $this->aAjouter, true)) {
                $this->aAjouter[] = $tag;
            }
        }
        $this->tagSaisie = '';
    }

    public function retirerTagAjout(string $nom): void
    {
        $this->aAjouter = array_values(array_filter($this->aAjouter, fn ($t) => $t !== $nom));
    }

    public function appliquer(): void
    {
        // Tag resté dans le champ de saisie : on l'ajoute avant d'appliquer
        if (trim($this->tagSaisie) !== '') {
            $this->ajouterTag();
        }

        (new TagService())->modifierParLot($this->itemIds, $this->aAjouter, $this->aRetirer);

        $this->ouvert = false;
        $this->dispatch('tags-par-lot-applique');
        $this->dispatch('arbre-modifie');
    }

    public function fermer(): void
    {
        $this->ouvert = false;
    }
};
?>

<div>
    @if ($ouvert)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:50;"
             wire:click.self="fermer">
            <div style="background:#fff;border-radius:10px;padding:1.25rem;width:min(480px,92vw);box-shadow:0 10px 40px rgba(0,0,0,.25);max-height:90vh;overflow:auto;">
                <h2 style="margin-top:0;font-size:1.1rem;">Tags par lot — {{ count($itemIds) }} objet(s)</h2>

                <form wire:submit="appliquer" style="display:flex;flex-direction:column;gap:1rem;">
                    {{-- Tags à ajouter --}}
                    <div style="display:flex;flex-direction:column;gap:.3rem;font-size:.85rem;color:#5e5c64;">
                        Ajouter ces tags
                        @if (count($aAjouter))
                            <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
                                @foreach ($aAjouter as $t)
                                    <span style="background:#e8f0fe;color:#1a73e8;border-radius:12px;padding:.1rem .5rem;font-size:.8rem;display:inline-flex;align-items:center;gap:.3rem;">
                                        {{ $t }}
                                        <button type="button" wire:click="retirerTagAjout('{{ $t }}')"
                                                style="border:none;background:transparent;color:#1a73e8;cursor:pointer;font-size:.9rem;line-height:1;">×</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <div style="display:flex;gap:.4rem;">
                            <input type="text" wire:model="tagSaisie" wire:keydown.enter.prevent="ajouterTag"
                                   list="vocab-tags-lot" placeholder="ajouter un tag (Entrée)…"
                                   style="flex:1;padding:.4rem;border:1px solid #c0bfbc;border-radius:6px;">
                            <datalist id="vocab-tags-lot">
                                @foreach ($this->vocabulaire as $v)
                                    <option value="{{ $v }}"></option>
                                @endforeach
                            </datalist>
                            <button type="button" wire:click="ajouterTag"
                                    style="padding:.4rem .7rem;border:1px solid #c0bfbc;background:#fff;border-radius:6px;cursor:pointer;">+</button>
                        </div>
                    </div>

                    {{-- Tags à retirer --}}
                    <div style="display:flex;flex-direction:column;gap:.3rem;font-size:.85rem;color:#5e5c64;">
                        Retirer ces tags (présents sur la sélection)
                        @if (count($this->tagsPresents))
                            <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                                @foreach ($this->tagsPresents as $tp)
                                    <label style="display:inline-flex;align-items:center;gap:.3rem;background:#faf7f5;border:1px solid #e0dedb;border-radius:12px;padding:.1rem .5rem;font-size:.8rem;cursor:pointer;">
                                        <input type="checkbox" wire:model="aRetirer" value="{{ $tp['name'] }}">
                                        {{ $tp['name'] }}
                                        <span style="color:#9a9996;">({{ $tp['count'] }})</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <span style="color:#9a9996;font-style:italic;">Aucun tag sur la sélection.</span>
                        @endif
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:.5rem;">
                        <button type="button" wire:click="fermer"
                                style="padding:.45rem .9rem;border:1px solid #c0bfbc;background:#fff;border-radius:6px;cursor:pointer;">Annuler</button>
                        <button type="submit"
                                style="padding:.45rem .9rem;border:none;background:#3584e4;color:#fff;border-radius:6px;cursor:pointer;">Appliquer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
