<?php

use App\Models\House;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $ouvert = false;
    public ?int $houseId = null;
    public string $name = '';
    public string $description = '';

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.max'      => 'Le nom ne doit pas dépasser 100 caractères.',
        ];
    }

    #[On('maison-creer')]
    public function ouvrirCreation(): void
    {
        $this->reinitialiser();
        $this->ouvert = true;
    }

    #[On('maison-editer')]
    public function ouvrirEdition(int $houseId): void
    {
        $this->reinitialiser();
        $maison = House::findOrFail($houseId);
        $this->houseId     = $maison->id;
        $this->name        = $maison->name;
        $this->description = $maison->description ?? '';
        $this->ouvert = true;
    }

    public function enregistrer(): void
    {
        $data = $this->validate();

        if ($this->houseId) {
            House::findOrFail($this->houseId)->update($data);
        } else {
            House::create($data);
        }

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
        $this->reset(['houseId', 'name', 'description']);
        $this->resetValidation();
    }
};
?>

<div>
    @if ($ouvert)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:50;"
             wire:click.self="fermer">
            <div style="background:#fff;border-radius:10px;padding:1.25rem;width:min(440px,92vw);box-shadow:0 10px 40px rgba(0,0,0,.25);">
                <h2 style="margin-top:0;font-size:1.1rem;">{{ $houseId ? 'Modifier la maison' : 'Nouvelle maison' }}</h2>

                <form wire:submit="enregistrer" style="display:flex;flex-direction:column;gap:.75rem;">
                    <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                        Nom
                        <input type="text" wire:model="name" maxlength="100"
                               style="padding:.45rem;border:1px solid #c0bfbc;border-radius:6px;font-size:1rem;">
                        @error('name') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
                    </label>

                    <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                        Description
                        <textarea wire:model="description" rows="3" maxlength="500"
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
