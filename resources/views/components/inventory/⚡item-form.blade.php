<?php

use App\Models\Item;
use App\Models\Tag;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $ouvert = false;

    /** Item en cours d'édition (null = création). */
    public ?int $itemId = null;

    /** Contexte de création. */
    public ?int $houseId = null;
    public ?int $parentId = null;

    /** Champs du formulaire. */
    public string $name = '';
    public string $description = '';
    public ?string $quantity = null;
    public string $unit = '';
    public bool $is_container = true;

    /** Tags : liste de noms (chips) + saisie courante. */
    public array $tags = [];
    public string $tagSaisie = '';

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'unit'        => ['nullable', 'string', 'max:20'],
            'is_container' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.max'      => 'Le nom ne doit pas dépasser 100 caractères.',
            'quantity.numeric' => 'La quantité doit être un nombre.',
            'quantity.min'  => 'La quantité ne peut pas être négative.',
        ];
    }

    /** Vocabulaire de tags existants (pour les suggestions). */
    #[Computed]
    public function vocabulaire(): array
    {
        return Tag::orderBy('name')->pluck('name')->all();
    }

    #[On('item-creer')]
    public function ouvrirCreation(int $houseId, ?int $parentId = null): void
    {
        $this->reinitialiser();
        $this->houseId = $houseId;
        $this->parentId = $parentId;
        $this->ouvert = true;
    }

    #[On('item-editer')]
    public function ouvrirEdition(int $itemId): void
    {
        $this->reinitialiser();
        $item = Item::with('tags')->findOrFail($itemId);
        $this->itemId       = $item->id;
        $this->houseId      = $item->house_id;
        $this->parentId     = $item->parent_id;
        $this->name         = $item->name;
        $this->description  = $item->description ?? '';
        $this->quantity     = $item->quantity !== null ? (string) (float) $item->quantity : null;
        $this->unit         = $item->unit ?? '';
        $this->is_container = (bool) $item->is_container;
        $this->tags         = $item->tags->pluck('name')->all();
        $this->ouvert = true;
    }

    /** Ajoute le tag saisi à la liste (normalisé, sans doublon). */
    public function ajouterTag(): void
    {
        foreach (explode(';', $this->tagSaisie) as $brut) {
            $tag = trim(mb_strtolower($brut));
            if ($tag !== '' && !in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }
        $this->tagSaisie = '';
    }

    public function retirerTag(string $nom): void
    {
        $this->tags = array_values(array_filter($this->tags, fn ($t) => $t !== $nom));
    }

    public function enregistrer(): void
    {
        // Tag resté dans le champ de saisie : on l'ajoute avant de valider
        if (trim($this->tagSaisie) !== '') {
            $this->ajouterTag();
        }

        $data = $this->validate();
        $data['quantity'] = ($data['quantity'] === null || $data['quantity'] === '') ? null : $data['quantity'];

        if ($this->itemId) {
            $item = Item::findOrFail($this->itemId);
            $item->update($data);
        } else {
            $item = Item::create($data + [
                'house_id'  => $this->houseId,
                'parent_id' => $this->parentId,
            ]);
        }

        // Synchronisation des tags (création à la volée du vocabulaire)
        $tagIds = collect($this->tags)
            ->map(fn (string $nom) => Tag::firstOrCreate(['name' => $nom])->id)
            ->all();
        $item->tags()->sync($tagIds);

        $this->ouvert = false;
        $this->dispatch('arbre-modifie');
    }

    public function fermer(): void
    {
        $this->ouvert = false;
        $this->resetValidation();
    }

    private function reinitialiser(): void
    {
        $this->reset(['itemId', 'houseId', 'parentId', 'name', 'description', 'quantity', 'unit', 'tags', 'tagSaisie']);
        $this->is_container = true;
        $this->resetValidation();
    }
};
?>

<div>
    @if ($ouvert)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:50;"
             wire:click.self="fermer">
            <div style="background:#fff;border-radius:10px;padding:1.25rem;width:min(480px,92vw);box-shadow:0 10px 40px rgba(0,0,0,.25);max-height:90vh;overflow:auto;">
                <h2 style="margin-top:0;font-size:1.1rem;">{{ $itemId ? 'Modifier l’objet' : 'Nouvel objet' }}</h2>

                <form wire:submit="enregistrer" style="display:flex;flex-direction:column;gap:.75rem;">
                    <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                        Nom
                        <input type="text" wire:model="name" maxlength="100"
                               style="padding:.45rem;border:1px solid #c0bfbc;border-radius:6px;font-size:1rem;">
                        @error('name') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
                    </label>

                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.9rem;">
                        <input type="checkbox" wire:model.live="is_container">
                        Est un conteneur (peut contenir d’autres objets)
                    </label>

                    @unless ($is_container)
                        <div style="display:flex;gap:.75rem;">
                            <label style="flex:1;display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                                Quantité
                                <input type="number" step="any" min="0" wire:model="quantity"
                                       style="padding:.45rem;border:1px solid #c0bfbc;border-radius:6px;">
                                @error('quantity') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
                            </label>
                            <label style="flex:1;display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                                Unité / Marque
                                <input type="text" wire:model="unit" maxlength="20"
                                       style="padding:.45rem;border:1px solid #c0bfbc;border-radius:6px;">
                                @error('unit') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    @endunless

                    {{-- Tags --}}
                    <div style="display:flex;flex-direction:column;gap:.3rem;font-size:.85rem;color:#5e5c64;">
                        Tags
                        @if (count($tags))
                            <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
                                @foreach ($tags as $t)
                                    <span style="background:#e8f0fe;color:#1a73e8;border-radius:12px;padding:.1rem .5rem;font-size:.8rem;display:inline-flex;align-items:center;gap:.3rem;">
                                        {{ $t }}
                                        <button type="button" wire:click="retirerTag('{{ $t }}')"
                                                style="border:none;background:transparent;color:#1a73e8;cursor:pointer;font-size:.9rem;line-height:1;">×</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <div style="display:flex;gap:.4rem;">
                            <input type="text" wire:model="tagSaisie" wire:keydown.enter.prevent="ajouterTag"
                                   list="vocab-tags" placeholder="ajouter un tag (Entrée)…"
                                   style="flex:1;padding:.4rem;border:1px solid #c0bfbc;border-radius:6px;">
                            <datalist id="vocab-tags">
                                @foreach ($this->vocabulaire as $v)
                                    <option value="{{ $v }}"></option>
                                @endforeach
                            </datalist>
                            <button type="button" wire:click="ajouterTag"
                                    style="padding:.4rem .7rem;border:1px solid #c0bfbc;background:#fff;border-radius:6px;cursor:pointer;">+</button>
                        </div>
                    </div>

                    <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                        Description
                        <textarea wire:model="description" rows="3" maxlength="1000"
                                  style="padding:.45rem;border:1px solid #c0bfbc;border-radius:6px;font-family:inherit;"></textarea>
                        @error('description') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
                    </label>

                    <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:.25rem;">
                        <button type="button" wire:click="fermer"
                                style="padding:.45rem .9rem;border:1px solid #c0bfbc;background:#fff;border-radius:6px;cursor:pointer;">Annuler</button>
                        <button type="submit"
                                style="padding:.45rem .9rem;border:none;background:#3584e4;color:#fff;border-radius:6px;cursor:pointer;">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
