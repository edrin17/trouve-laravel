<?php

namespace Tests\Feature\Livewire;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_ajoute_des_tags_a_la_creation(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'Marteau')
            ->set('tagSaisie', 'bricolage')
            ->call('ajouterTag')
            ->assertSet('tags', ['bricolage'])
            ->set('tagSaisie', 'outil')
            ->call('ajouterTag')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $item = Item::where('name', 'Marteau')->first();
        $this->assertEqualsCanonicalizing(['bricolage', 'outil'], $item->tags->pluck('name')->all());
    }

    public function test_ajoute_tag_normalise_et_sans_doublon(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('tagSaisie', '  FRAGILE ')
            ->call('ajouterTag')
            ->set('tagSaisie', 'fragile')
            ->call('ajouterTag')
            ->assertSet('tags', ['fragile']); // normalisé minuscule + pas de doublon
    }

    public function test_saisie_multiple_separee_par_point_virgule(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('tagSaisie', 'a; b; c')
            ->call('ajouterTag')
            ->assertSet('tags', ['a', 'b', 'c']);
    }

    public function test_retire_un_tag(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('tags', ['x', 'y', 'z'])
            ->call('retirerTag', 'y')
            ->assertSet('tags', ['x', 'z']);
    }

    public function test_edition_precharge_les_tags(): void
    {
        $item = Item::factory()->create();
        $item->tags()->attach(Tag::factory()->create(['name' => 'existant'])->id);

        Livewire::test(self::COMPONENT)
            ->call('ouvrirEdition', $item->id)
            ->assertSet('tags', ['existant']);
    }

    public function test_enregistre_le_tag_reste_dans_la_saisie(): void
    {
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'Objet')
            ->set('tagSaisie', 'oubli') // non confirmé par Entrée
            ->call('enregistrer')
            ->assertHasNoErrors();

        $item = Item::where('name', 'Objet')->first();
        $this->assertSame(['oubli'], $item->tags->pluck('name')->all());
    }

    public function test_upload_d_une_image_a_la_creation(): void
    {
        Storage::fake(ImageService::DISQUE);
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'Objet photo')
            ->set('photo', UploadedFile::fake()->image('p.jpg', 500, 500))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $item = Item::where('name', 'Objet photo')->first();
        $this->assertNotNull($item->image_filename);
        Storage::disk(ImageService::DISQUE)->assertExists(ImageService::DOSSIER . '/' . $item->image_filename);
    }

    public function test_image_non_image_refusee(): void
    {
        Storage::fake(ImageService::DISQUE);
        $house = House::factory()->create();

        Livewire::test(self::COMPONENT)
            ->call('ouvrirCreation', $house->id, null)
            ->set('name', 'Objet')
            ->set('photo', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('enregistrer')
            ->assertHasErrors(['photo']);
    }

    public function test_remplacement_d_image_supprime_l_ancienne(): void
    {
        Storage::fake(ImageService::DISQUE);
        $ancienne = (new ImageService())->stocker(UploadedFile::fake()->image('vieux.jpg'));
        $item = Item::factory()->create(['image_filename' => $ancienne]);

        Livewire::test(self::COMPONENT)
            ->call('ouvrirEdition', $item->id)
            ->set('photo', UploadedFile::fake()->image('neuve.jpg'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertNotSame($ancienne, $item->image_filename);
        Storage::disk(ImageService::DISQUE)->assertMissing(ImageService::DOSSIER . '/' . $ancienne);
        Storage::disk(ImageService::DISQUE)->assertExists(ImageService::DOSSIER . '/' . $item->image_filename);
    }

    public function test_suppression_d_image(): void
    {
        Storage::fake(ImageService::DISQUE);
        $fichier = (new ImageService())->stocker(UploadedFile::fake()->image('x.jpg'));
        $item = Item::factory()->create(['image_filename' => $fichier]);

        Livewire::test(self::COMPONENT)
            ->call('ouvrirEdition', $item->id)
            ->call('supprimerImage')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull($item->fresh()->image_filename);
        Storage::disk(ImageService::DISQUE)->assertMissing(ImageService::DOSSIER . '/' . $fichier);
    }

    public function test_suppression_d_item_supprime_son_image(): void
    {
        Storage::fake(ImageService::DISQUE);
        $fichier = (new ImageService())->stocker(UploadedFile::fake()->image('x.jpg'));
        $item = Item::factory()->create(['image_filename' => $fichier]);

        $item->delete(); // déclenche l'event deleting du modèle

        Storage::disk(ImageService::DISQUE)->assertMissing(ImageService::DOSSIER . '/' . $fichier);
    }
}
