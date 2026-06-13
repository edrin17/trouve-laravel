<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagService();
    }

    public function test_ajoute_un_tag_existant_a_plusieurs_items(): void
    {
        $items = Item::factory()->count(3)->create();
        $tag = Tag::factory()->create(['name' => 'fragile']);

        $this->service->modifierParLot($items->pluck('id')->all(), ['fragile'], []);

        foreach ($items as $item) {
            $this->assertTrue($item->fresh()->tags->contains($tag));
        }
    }

    public function test_cree_le_tag_a_la_volee_si_absent(): void
    {
        $items = Item::factory()->count(2)->create();

        $this->service->modifierParLot($items->pluck('id')->all(), ['nouveau'], []);

        $this->assertDatabaseHas('tags', ['name' => 'nouveau']);
        $this->assertCount(1, $items->first()->fresh()->tags);
    }

    public function test_retire_un_tag_du_groupe(): void
    {
        $items = Item::factory()->count(2)->create();
        $tag = Tag::factory()->create(['name' => 'obsolete']);
        $items->each(fn (Item $i) => $i->tags()->attach($tag->id));

        $this->service->modifierParLot($items->pluck('id')->all(), [], ['obsolete']);

        foreach ($items as $item) {
            $this->assertCount(0, $item->fresh()->tags);
        }
        // le tag reste dans le vocabulaire
        $this->assertDatabaseHas('tags', ['name' => 'obsolete']);
    }

    public function test_ajout_et_retrait_simultanes(): void
    {
        $item = Item::factory()->create();
        $ancien = Tag::factory()->create(['name' => 'ancien']);
        $item->tags()->attach($ancien->id);

        $this->service->modifierParLot([$item->id], ['recent'], ['ancien']);

        $noms = $item->fresh()->tags->pluck('name')->all();
        $this->assertSame(['recent'], $noms);
    }

    public function test_ajout_idempotent_pas_de_doublon(): void
    {
        $item = Item::factory()->create();
        $tag = Tag::factory()->create(['name' => 'unique']);
        $item->tags()->attach($tag->id);

        // ré-ajouter le même tag ne crée pas de doublon (clé primaire composite)
        $this->service->modifierParLot([$item->id], ['unique'], []);

        $this->assertCount(1, $item->fresh()->tags);
    }

    public function test_liste_vide_ne_fait_rien(): void
    {
        $tag = Tag::factory()->create(['name' => 'intact']);

        $this->service->modifierParLot([], ['intact'], []);

        $this->assertSame(0, \DB::table('item_tag')->count());
    }

    public function test_vocabulaire_avec_compteur(): void
    {
        $t1 = Tag::factory()->create(['name' => 'aaa']);
        $t2 = Tag::factory()->create(['name' => 'bbb']);
        Item::factory()->count(2)->create()->each(fn (Item $i) => $i->tags()->attach($t1->id));

        $vocab = $this->service->vocabulaireAvecCompteur();

        // trié par nom : aaa (2 items), bbb (0 item)
        $this->assertSame('aaa', $vocab->first()->name);
        $this->assertSame(2, $vocab->first()->items_count);
        $this->assertSame(0, $vocab->last()->items_count);
    }

    public function test_renomme_un_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'ancien']);

        $this->service->renommer($tag->id, 'NOUVEAU');

        $this->assertSame('nouveau', $tag->fresh()->name); // normalisé minuscule
    }

    public function test_renommer_vers_un_nom_existant_fusionne(): void
    {
        $source = Tag::factory()->create(['name' => 'source']);
        $cible  = Tag::factory()->create(['name' => 'cible']);

        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();
        $item1->tags()->attach($source->id);
        $item2->tags()->attach([$source->id, $cible->id]); // déjà les deux

        $this->service->renommer($source->id, 'cible');

        // le tag source a disparu
        $this->assertNull(Tag::find($source->id));
        // les items du source sont rattachés à cible, sans doublon
        $this->assertTrue($item1->fresh()->tags->contains('id', $cible->id));
        $this->assertSame(1, $item2->fresh()->tags()->where('tags.id', $cible->id)->count());
    }

    public function test_renommer_avec_nom_vide_ne_fait_rien(): void
    {
        $tag = Tag::factory()->create(['name' => 'stable']);

        $this->service->renommer($tag->id, '   ');

        $this->assertSame('stable', $tag->fresh()->name);
    }

    public function test_supprime_un_tag_du_vocabulaire(): void
    {
        $tag = Tag::factory()->create(['name' => 'jetable']);
        $item = Item::factory()->create();
        $item->tags()->attach($tag->id);

        $this->service->supprimerDuVocabulaire($tag->id);

        $this->assertNull(Tag::find($tag->id));
        // l'association pivot part en cascade
        $this->assertCount(0, $item->fresh()->tags);
    }
}
