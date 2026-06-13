<?php

namespace Tests\Feature\Livewire;

use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagManagerTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'inventory.tag-manager';

    public function test_ouvre_et_liste_les_tags(): void
    {
        Tag::factory()->create(['name' => 'alpha']);
        Tag::factory()->create(['name' => 'beta']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir')
            ->assertSet('ouvert', true)
            ->assertSee('alpha')
            ->assertSee('beta');
    }

    public function test_renomme_un_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'ancien']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir')
            ->call('editer', $tag->id)
            ->assertSet('nomEdite', 'ancien')
            ->set('nomEdite', 'nouveau')
            ->call('renommer')
            ->assertHasNoErrors()
            ->assertDispatched('arbre-modifie')
            ->assertSet('enEdition', null);

        $this->assertSame('nouveau', $tag->fresh()->name);
    }

    public function test_renommer_vers_un_nom_existant_fusionne(): void
    {
        $source = Tag::factory()->create(['name' => 'source']);
        $cible  = Tag::factory()->create(['name' => 'cible']);
        $item = Item::factory()->create();
        $item->tags()->attach($source->id);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir')
            ->call('editer', $source->id)
            ->set('nomEdite', 'cible')
            ->call('renommer')
            ->assertHasNoErrors();

        $this->assertNull(Tag::find($source->id));
        $this->assertTrue($item->fresh()->tags->contains('id', $cible->id));
    }

    public function test_nom_vide_refuse(): void
    {
        $tag = Tag::factory()->create(['name' => 'stable']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir')
            ->call('editer', $tag->id)
            ->set('nomEdite', '')
            ->call('renommer')
            ->assertHasErrors(['nomEdite' => 'required']);

        $this->assertSame('stable', $tag->fresh()->name);
    }

    public function test_supprime_un_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'jetable']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir')
            ->call('supprimer', $tag->id)
            ->assertDispatched('arbre-modifie');

        $this->assertNull(Tag::find($tag->id));
    }

    public function test_annuler_edition_remet_a_zero(): void
    {
        $tag = Tag::factory()->create(['name' => 'inchangé']);

        Livewire::test(self::COMPONENT)
            ->call('ouvrir')
            ->call('editer', $tag->id)
            ->set('nomEdite', 'brouillon')
            ->call('annulerEdition')
            ->assertSet('enEdition', null)
            ->assertSet('nomEdite', '');

        $this->assertSame('inchangé', $tag->fresh()->name);
    }
}
