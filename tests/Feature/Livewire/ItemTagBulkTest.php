<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemTagBulkTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'inventory.item-tag-bulk';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create()); // le tree (rendu par un test) vit derrière le middleware auth
    }

    public function test_ouvrir_preremplit_les_items_et_ouvre_la_modale(): void
    {
        $a = Item::factory()->create();
        $b = Item::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', [$a->id, $b->id])
            ->assertSet('ouvert', true)
            ->assertSet('itemIds', [$a->id, $b->id]);
    }

    public function test_ajoute_un_tag_a_tous_les_items(): void
    {
        $a = Item::factory()->create();
        $b = Item::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', [$a->id, $b->id])
            ->set('tagSaisie', 'fragile')
            ->call('ajouterTag')
            ->call('appliquer')
            ->assertDispatched('tags-par-lot-applique')
            ->assertDispatched('arbre-modifie')
            ->assertSet('ouvert', false);

        $this->assertTrue($a->fresh()->tags->pluck('name')->contains('fragile'));
        $this->assertTrue($b->fresh()->tags->pluck('name')->contains('fragile'));
    }

    public function test_la_saisie_restante_est_appliquee_sans_clic_sur_ajouter(): void
    {
        $a = Item::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', [$a->id])
            ->set('tagSaisie', 'cuisine')
            ->call('appliquer');

        $this->assertTrue($a->fresh()->tags->pluck('name')->contains('cuisine'));
    }

    public function test_normalise_les_tags_saisis(): void
    {
        $a = Item::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', [$a->id])
            ->set('tagSaisie', '  Outils ; CUISINE ')
            ->call('ajouterTag')
            ->assertSet('aAjouter', ['outils', 'cuisine']);
    }

    public function test_retire_un_tag_de_tous_les_items(): void
    {
        $tag = Tag::factory()->create(['name' => 'vieux']);
        $a = Item::factory()->create();
        $b = Item::factory()->create();
        $a->tags()->attach($tag);
        $b->tags()->attach($tag);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', [$a->id, $b->id])
            ->set('aRetirer', ['vieux'])
            ->call('appliquer');

        $this->assertFalse($a->fresh()->tags->pluck('name')->contains('vieux'));
        $this->assertFalse($b->fresh()->tags->pluck('name')->contains('vieux'));
    }

    public function test_tags_presents_liste_les_tags_de_la_selection_avec_compteur(): void
    {
        $commun = Tag::factory()->create(['name' => 'commun']);
        $solo = Tag::factory()->create(['name' => 'solo']);
        $autre = Tag::factory()->create(['name' => 'hors-selection']);

        $a = Item::factory()->create();
        $b = Item::factory()->create();
        $horsSelection = Item::factory()->create();

        $a->tags()->attach([$commun->id, $solo->id]);
        $b->tags()->attach($commun->id);
        $horsSelection->tags()->attach($autre->id);

        $presents = collect(
            Livewire::test(self::COMPONENT)
                ->call('ouvrir', [$a->id, $b->id])
                ->get('tagsPresents')
        )->keyBy('name');

        $this->assertSame(2, $presents['commun']['count']);
        $this->assertSame(1, $presents['solo']['count']);
        $this->assertFalse($presents->has('hors-selection'));
    }

    public function test_selection_vide_ne_fait_rien(): void
    {
        $tag = Tag::factory()->create(['name' => 'x']);
        $item = Item::factory()->create();
        $item->tags()->attach($tag);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir', [])
            ->set('tagSaisie', 'nouveau')
            ->call('ajouterTag')
            ->call('appliquer');

        // aucun item ciblé : le tag "nouveau" n'est créé pour personne
        $this->assertSame(0, Tag::where('name', 'nouveau')->count());
    }

    public function test_le_mode_selection_affiche_les_cases_a_cocher(): void
    {
        $maison = House::factory()->create();
        $item = Item::factory()->create(['house_id' => $maison->id]);

        Livewire::test('inventory.tree')
            ->set('modeSelection', true)
            ->assertSee('Sélectionner cet objet')
            ->call('basculerSelection')
            ->assertSet('modeSelection', false);
    }
}
