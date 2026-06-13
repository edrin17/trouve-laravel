<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HouseFormTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'inventory.house-form';

    public function test_cree_une_maison(): void
    {
        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation')
            ->set('name', 'Résidence secondaire')
            ->set('description', 'au bord de mer')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertDispatched('arbre-modifie');

        $this->assertDatabaseHas('houses', ['name' => 'Résidence secondaire']);
    }

    public function test_nom_obligatoire(): void
    {
        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation')
            ->set('name', '')
            ->call('enregistrer')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_edition_precharge_et_modifie(): void
    {
        $maison = House::factory()->create(['name' => 'Ancien']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrirEdition', $maison->id)
            ->assertSet('name', 'Ancien')
            ->set('name', 'Nouveau')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau', $maison->fresh()->name);
    }

    public function test_suppression_maison_cascade_le_contenu(): void
    {
        $maison = House::factory()->create();
        Item::factory()->count(3)->create(['house_id' => $maison->id]);

        Livewire::test('inventory.tree')
            ->call('supprimerMaison', $maison->id);

        $this->assertDatabaseMissing('houses', ['id' => $maison->id]);
        $this->assertSame(0, Item::count());
    }
}
