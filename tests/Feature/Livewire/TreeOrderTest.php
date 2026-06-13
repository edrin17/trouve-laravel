<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TreeOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_objets_simples_apparaissent_avant_les_conteneurs(): void
    {
        $maison = House::factory()->create();
        // créés volontairement dans le désordre
        Item::factory()->create(['house_id' => $maison->id, 'name' => 'Zèbre conteneur', 'is_container' => true]);
        Item::factory()->create(['house_id' => $maison->id, 'name' => 'Objet simple', 'is_container' => false]);
        Item::factory()->create(['house_id' => $maison->id, 'name' => 'Armoire conteneur', 'is_container' => true]);

        // attendu : objet simple d'abord, puis conteneurs triés par nom
        Livewire::test('inventory.tree')
            ->assertSeeInOrder(['Objet simple', 'Armoire conteneur', 'Zèbre conteneur']);
    }

    public function test_tri_applique_aussi_dans_les_conteneurs(): void
    {
        $maison = House::factory()->create();
        $parent = Item::factory()->create(['house_id' => $maison->id, 'is_container' => true, 'name' => 'Parent']);
        Item::factory()->childOf($parent)->create(['name' => 'Sous-conteneur', 'is_container' => true]);
        Item::factory()->childOf($parent)->create(['name' => 'Sous-objet', 'is_container' => false]);

        Livewire::test('inventory.tree')
            ->assertSeeInOrder(['Parent', 'Sous-objet', 'Sous-conteneur']);
    }

    public function test_objets_simples_tries_par_nom_entre_eux(): void
    {
        $maison = House::factory()->create();
        Item::factory()->create(['house_id' => $maison->id, 'name' => 'Banane', 'is_container' => false]);
        Item::factory()->create(['house_id' => $maison->id, 'name' => 'Abricot', 'is_container' => false]);

        Livewire::test('inventory.tree')
            ->assertSeeInOrder(['Abricot', 'Banane']);
    }
}
