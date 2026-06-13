<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemFormTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'inventory.item-form';

    public function test_creation_ajoute_un_item(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'Nouvelle boîte')
            ->set('is_container', true)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertDispatched('arbre-modifie');

        $this->assertDatabaseHas('items', [
            'name'      => 'Nouvelle boîte',
            'house_id'  => $house->id,
            'parent_id' => null,
        ]);
    }

    public function test_creation_sous_un_parent(): void
    {
        $parent = Item::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $parent->house_id, $parent->id)
            ->set('name', 'Enfant')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('items', [
            'name'      => 'Enfant',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_nom_obligatoire(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', '')
            ->call('enregistrer')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_quantite_negative_refusee(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'Objet')
            ->set('is_container', false)
            ->set('quantity', '-5')
            ->call('enregistrer')
            ->assertHasErrors(['quantity']);
    }

    public function test_edition_modifie_item(): void
    {
        $item = Item::factory()->create(['name' => 'Ancien nom']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrirEdition', $item->id)
            ->assertSet('name', 'Ancien nom')
            ->set('name', 'Nouveau nom')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau nom', $item->fresh()->name);
    }

    public function test_fermer_remet_a_zero(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'brouillon')
            ->call('fermer')
            ->assertSet('ouvert', false);
    }
}
