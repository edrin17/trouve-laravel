<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItemService();
    }

    public function test_deplacer_change_le_parent(): void
    {
        $a = Item::factory()->create();
        $b = Item::factory()->create(['house_id' => $a->house_id]);

        $this->service->deplacer($b, $a->id);

        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_deplacer_vers_racine_avec_null(): void
    {
        $parent = Item::factory()->create();
        $enfant = Item::factory()->childOf($parent)->create();

        $this->service->deplacer($enfant, null);

        $this->assertNull($enfant->fresh()->parent_id);
    }

    public function test_ids_descendants_parcourt_tout_le_sous_arbre(): void
    {
        $racine = Item::factory()->create();
        $niv1 = Item::factory()->childOf($racine)->create();
        $niv2 = Item::factory()->childOf($niv1)->create();
        $autre = Item::factory()->create(); // hors sous-arbre

        $ids = $this->service->idsDescendants($racine);

        sort($ids);
        $attendu = [$racine->id, $niv1->id, $niv2->id];
        sort($attendu);
        $this->assertSame($attendu, $ids);
        $this->assertNotContains($autre->id, $ids);
    }

    public function test_deplacer_vers_maison_bascule_tout_le_sous_arbre(): void
    {
        $maisonA = House::factory()->create();
        $maisonB = House::factory()->create();

        $racine = Item::factory()->create(['house_id' => $maisonA->id]);
        $enfant = Item::factory()->childOf($racine)->create();
        $petit = Item::factory()->childOf($enfant)->create();

        $this->service->deplacerVersMaison($racine, $maisonB->id);

        // tout le sous-arbre a changé de maison
        $this->assertSame($maisonB->id, $racine->fresh()->house_id);
        $this->assertSame($maisonB->id, $enfant->fresh()->house_id);
        $this->assertSame($maisonB->id, $petit->fresh()->house_id);
    }

    public function test_deplacer_vers_maison_preserve_structure_interne(): void
    {
        $maisonA = House::factory()->create();
        $maisonB = House::factory()->create();

        $racine = Item::factory()->create(['house_id' => $maisonA->id]);
        $enfant = Item::factory()->childOf($racine)->create();

        $this->service->deplacerVersMaison($racine, $maisonB->id);

        // la racine passe à la racine de B (parent null)…
        $this->assertNull($racine->fresh()->parent_id);
        // …mais l'enfant reste rattaché à la racine
        $this->assertSame($racine->id, $enfant->fresh()->parent_id);
    }

    public function test_est_descendant_detecte_le_sous_arbre(): void
    {
        $racine = Item::factory()->create();
        $enfant = Item::factory()->childOf($racine)->create();
        $etranger = Item::factory()->create();

        $this->assertTrue($this->service->estDescendant($racine, $enfant));
        $this->assertTrue($this->service->estDescendant($racine, $racine)); // lui-même
        $this->assertFalse($this->service->estDescendant($racine, $etranger));
    }
}
