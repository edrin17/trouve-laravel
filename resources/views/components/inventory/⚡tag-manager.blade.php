<?php

use App\Models\Tag;
use App\Services\TagService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $ouvert = false;

    /** Id du tag en cours d'édition inline (null = aucun). */
    public ?int $enEdition = null;

    /** Nom saisi pendant l'édition inline. */
    public string $nomEdite = '';

    #[On('tags-gerer')]
    public function ouvrir(): void
    {
        $this->reset(['enEdition', 'nomEdite']);
        $this->resetValidation();
        $this->ouvert = true;
    }

    /** Vocabulaire complet avec compteur d'items. */
    #[Computed]
    public function tags()
    {
        return (new TagService())->vocabulaireAvecCompteur();
    }

    public function editer(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }
        $this->enEdition = $tag->id;
        $this->nomEdite = $tag->name;
        $this->resetValidation();
    }

    public function annulerEdition(): void
    {
        $this->reset(['enEdition', 'nomEdite']);
        $this->resetValidation();
    }

    public function renommer(): void
    {
        $this->validate(
            ['nomEdite' => ['required', 'string', 'max:50']],
            [
                'nomEdite.required' => 'Le nom est obligatoire.',
                'nomEdite.max'      => 'Le nom ne doit pas dépasser 50 caractères.',
            ],
        );

        (new TagService())->renommer($this->enEdition, $this->nomEdite);

        $this->reset(['enEdition', 'nomEdite']);
        unset($this->tags);
        $this->dispatch('arbre-modifie');
    }

    public function supprimer(int $tagId): void
    {
        (new TagService())->supprimerDuVocabulaire($tagId);
        unset($this->tags);
        $this->dispatch('arbre-modifie');
    }

    public function fermer(): void
    {
        $this->ouvert = false;
        $this->reset(['enEdition', 'nomEdite']);
        $this->resetValidation();
    }
};
?>

<div>
    @if ($ouvert)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:50;"
             wire:click.self="fermer">
            <div style="background:#fff;border-radius:10px;padding:1.25rem;width:min(480px,92vw);box-shadow:0 10px 40px rgba(0,0,0,.25);max-height:90vh;overflow:auto;">
                <h2 style="margin-top:0;font-size:1.1rem;">Gérer les tags</h2>

                @if ($this->tags->isEmpty())
                    <p style="color:#9a9996;font-style:italic;">Aucun tag dans le vocabulaire.</p>
                @else
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.35rem;">
                        @foreach ($this->tags as $tag)
                            <li wire:key="tag-{{ $tag->id }}"
                                style="display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;background:#faf7f5;border:1px solid #e0dedb;border-radius:6px;">
                                @if ($enEdition === $tag->id)
                                    <input type="text" wire:model="nomEdite" maxlength="50"
                                           wire:keydown.enter.prevent="renommer"
                                           style="flex:1;padding:.3rem;border:1px solid #c0bfbc;border-radius:6px;">
                                    <button type="button" wire:click="renommer"
                                            style="border:none;background:#3584e4;color:#fff;border-radius:6px;padding:.25rem .6rem;cursor:pointer;font-size:.85rem;">Valider</button>
                                    <button type="button" wire:click="annulerEdition"
                                            style="border:1px solid #c0bfbc;background:#fff;border-radius:6px;padding:.25rem .6rem;cursor:pointer;font-size:.85rem;">Annuler</button>
                                @else
                                    <span style="flex:1;">{{ $tag->name }}
                                        <span style="color:#9a9996;font-size:.85rem;">({{ $tag->items_count }})</span>
                                    </span>
                                    <button type="button" title="Renommer" wire:click="editer({{ $tag->id }})"
                                            style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">✏️</button>
                                    <button type="button" title="Supprimer du vocabulaire"
                                            wire:click="supprimer({{ $tag->id }})"
                                            wire:confirm="Supprimer le tag « {{ $tag->name }} » ? Il sera retiré de {{ $tag->items_count }} objet(s)."
                                            style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">🗑️</button>
                                @endif
                            </li>
                            @if ($enEdition === $tag->id)
                                @error('nomEdite')
                                    <span style="color:#c01c28;font-size:.8rem;padding-left:.6rem;">{{ $message }}</span>
                                @enderror
                            @endif
                        @endforeach
                    </ul>
                @endif

                <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" wire:click="fermer"
                            style="padding:.45rem .9rem;border:1px solid #c0bfbc;background:#fff;border-radius:6px;cursor:pointer;">Fermer</button>
                </div>
            </div>
        </div>
    @endif
</div>
