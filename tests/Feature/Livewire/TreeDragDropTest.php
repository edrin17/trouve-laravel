<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TreeDragDropTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'inventory.tree';

    public function test_drop_sur_conteneur_change_le_parent(): void
    {
        $maison = House::factory()->create();
        $conteneur = Item::factory()->create(['house_id' => $maison->id, 'is_container' => true]);
        $item = Item::factory()->create(['house_id' => $maison->id]);

        Livewire::test(self::COMPONENT)
            ->call('deplacerVers', $item->id, "item:{$conteneur->id}");

        $this->assertSame($conteneur->id, $item->fresh()->parent_id);
    }

    public function test_drop_sur_maison_met_a_la_racine(): void
    {
        $maisonA = House::factory()->create();
        $maisonB = House::factory()->create();
        $item = Item::factory()->create(['house_id' => $maisonA->id]);

        Livewire::test(self::COMPONENT)
            ->call('deplacerVers', $item->id, "house:{$maisonB->id}");

        $item->refresh();
        $this->assertSame($maisonB->id, $item->house_id);
        $this->assertNull($item->parent_id);
    }

    public function test_drop_dans_son_propre_sous_arbre_est_ignore(): void
    {
        $racine = Item::factory()->create(['is_container' => true]);
        $enfant = Item::factory()->childOf($racine)->state(['is_container' => true])->create();

        // tenter de déplacer la racine dans son propre enfant
        Livewire::test(self::COMPONENT)
            ->call('deplacerVers', $racine->id, "item:{$enfant->id}");

        // inchangé
        $this->assertNull($racine->fresh()->parent_id);
    }

    public function test_drop_sur_un_non_conteneur_est_ignore(): void
    {
        $maison = House::factory()->create();
        $feuille = Item::factory()->create(['house_id' => $maison->id, 'is_container' => false]);
        $item = Item::factory()->create(['house_id' => $maison->id]);

        Livewire::test(self::COMPONENT)
            ->call('deplacerVers', $item->id, "item:{$feuille->id}");

        $this->assertNull($item->fresh()->parent_id);
    }

    public function test_drop_sur_parent_actuel_est_ignore(): void
    {
        $parent = Item::factory()->create(['is_container' => true]);
        $enfant = Item::factory()->childOf($parent)->create();

        // déposer sur son parent actuel : pas d'erreur, reste rattaché
        Livewire::test(self::COMPONENT)
            ->call('deplacerVers', $enfant->id, "item:{$parent->id}");

        $this->assertSame($parent->id, $enfant->fresh()->parent_id);
    }

    public function test_drop_item_inexistant_ne_casse_pas(): void
    {
        $maison = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('deplacerVers', 99999, "house:{$maison->id}")
            ->assertOk();
    }
}
