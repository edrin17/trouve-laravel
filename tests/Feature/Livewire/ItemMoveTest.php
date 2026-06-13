<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemMoveTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'inventory.item-move';

    public function test_deplace_vers_la_racine_d_une_autre_maison(): void
    {
        $maisonA = House::factory()->create();
        $maisonB = House::factory()->create();
        $item = Item::factory()->create(['house_id' => $maisonA->id]);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', $item->id)
            ->set('destination', "house:{$maisonB->id}")
            ->call('deplacer')
            ->assertDispatched('arbre-modifie');

        $item->refresh();
        $this->assertSame($maisonB->id, $item->house_id);
        $this->assertNull($item->parent_id);
    }

    public function test_deplace_sous_un_conteneur_meme_maison(): void
    {
        $maison = House::factory()->create();
        $cible = Item::factory()->create(['house_id' => $maison->id, 'is_container' => true]);
        $item = Item::factory()->create(['house_id' => $maison->id]);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', $item->id)
            ->set('destination', "item:{$cible->id}")
            ->call('deplacer');

        $this->assertSame($cible->id, $item->fresh()->parent_id);
    }

    public function test_deplace_sous_conteneur_autre_maison_bascule_le_sous_arbre(): void
    {
        $maisonA = House::factory()->create();
        $maisonB = House::factory()->create();
        $cible = Item::factory()->create(['house_id' => $maisonB->id, 'is_container' => true]);

        $item = Item::factory()->create(['house_id' => $maisonA->id]);
        $enfant = Item::factory()->childOf($item)->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', $item->id)
            ->set('destination', "item:{$cible->id}")
            ->call('deplacer');

        $this->assertSame($cible->id, $item->fresh()->parent_id);
        $this->assertSame($maisonB->id, $item->fresh()->house_id);
        // le sous-arbre suit
        $this->assertSame($maisonB->id, $enfant->fresh()->house_id);
    }

    public function test_destination_obligatoire(): void
    {
        $item = Item::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', $item->id)
            ->set('destination', '')
            ->call('deplacer')
            ->assertHasErrors('destination');
    }

    public function test_destinations_excluent_l_item_et_son_sous_arbre(): void
    {
        $maison = House::factory()->create();
        $item = Item::factory()->create(['house_id' => $maison->id, 'is_container' => true]);
        $enfant = Item::factory()->childOf($item)->state(['is_container' => true])->create();

        $component = Livewire::test(self::COMPONENT)->call('ouvrir', $item->id);
        $destinations = collect($component->get('destinations'))->pluck('value');

        // ni l'item déplacé ni son enfant ne doivent figurer comme destination
        $this->assertFalse($destinations->contains("item:{$item->id}"));
        $this->assertFalse($destinations->contains("item:{$enfant->id}"));
        // la racine de la maison reste proposée
        $this->assertTrue($destinations->contains("house:{$maison->id}"));
    }
}
