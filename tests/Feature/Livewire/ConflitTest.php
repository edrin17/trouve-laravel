<?php

namespace Tests\Feature\Livewire;

use App\Models\Item;
use App\Models\User;
use App\Services\ConflitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Résolution des conflits matérialisés (étape 4 — UI).
 * Cf. docs/conception_mode_hors_connexion.md §6.
 */
class ConflitTest extends TestCase
{
    use RefreshDatabase;

    private const TREE = 'inventory.tree';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create()); // le tree vit derrière auth
    }

    /** Crée un couple original + doublon en conflit, renvoie [original, doublon]. */
    private function couple(): array
    {
        $original = Item::factory()->create(['name' => 'Perceuse', 'version' => 2]);
        $doublon = Item::factory()->create([
            'name'       => 'Perceuse (conflit — Bob)',
            'house_id'   => $original->house_id,
            'parent_id'  => $original->parent_id,
            'en_conflit' => true,
            'conflit_de' => $original->uuid,
        ]);

        return [$original, $doublon];
    }

    public function test_garder_supprime_l_original_leve_le_marquage_et_nettoie_le_nom(): void
    {
        [$original, $doublon] = $this->couple();

        $this->assertTrue((new ConflitService())->garder($doublon->fresh()));

        $this->assertDatabaseMissing('items', ['id' => $original->id]);
        $gagnant = $doublon->fresh();
        $this->assertFalse($gagnant->en_conflit);
        $this->assertNull($gagnant->conflit_de);
        // le suffixe « (conflit — Bob) » est retiré
        $this->assertSame('Perceuse', $gagnant->name);
    }

    public function test_garder_serveur_supprime_le_doublon_et_garde_l_original(): void
    {
        [$original, $doublon] = $this->couple();

        $this->assertTrue((new ConflitService())->garderServeur($doublon->fresh()));

        // le doublon disparaît, l'original (intact) subsiste
        $this->assertDatabaseMissing('items', ['id' => $doublon->id]);
        $this->assertDatabaseHas('items', ['id' => $original->id, 'name' => 'Perceuse']);
        $this->assertFalse($original->fresh()->en_conflit);
    }

    public function test_garder_serveur_reattache_les_enfants_du_doublon_a_l_original(): void
    {
        [$original, $doublon] = $this->couple();
        $enfant = Item::factory()->create([
            'house_id'  => $doublon->house_id,
            'parent_id' => $doublon->id,
        ]);

        (new ConflitService())->garderServeur($doublon->fresh());

        // l'enfant du doublon n'est pas perdu : il passe sous l'original
        $this->assertSame($original->id, $enfant->fresh()->parent_id);
    }

    public function test_accepter_nettoie_aussi_le_nom(): void
    {
        [, $doublon] = $this->couple();

        (new ConflitService())->accepter($doublon->fresh());

        $this->assertSame('Perceuse', $doublon->fresh()->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nomsDeConflit')]
    public function test_nettoyage_du_suffixe_de_conflit(string $entree, string $attendu): void
    {
        $this->assertSame($attendu, (new ConflitService())->nomNettoye($entree));
    }

    public static function nomsDeConflit(): array
    {
        return [
            ['Perceuse (conflit — Bob)', 'Perceuse'],
            ['Perceuse (conflit)', 'Perceuse'],
            ['Perceuse', 'Perceuse'],                    // pas de suffixe → inchangé
            ['Boîte (rangement) (conflit — Alice)', 'Boîte (rangement)'], // ne retire que le suffixe final
        ];
    }

    /**
     * Invariant sync : tout item/maison seedé doit porter un uuid + une version.
     * Garde-fou contre WithoutModelEvents (qui muterait les hooks `creating` et
     * sèmerait des lignes à uuid NULL → conflits non rattachables).
     */
    public function test_le_seeder_genere_uuid_et_version(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $this->assertSame(0, Item::whereNull('uuid')->count(), 'des items seedés sans uuid');
        $this->assertSame(0, Item::whereNull('version')->count(), 'des items seedés sans version');
    }

    public function test_garder_reattache_les_enfants_de_l_original_au_doublon(): void
    {
        [$original, $doublon] = $this->couple();
        $enfant = Item::factory()->create([
            'house_id'  => $original->house_id,
            'parent_id' => $original->id,
        ]);

        (new ConflitService())->garder($doublon->fresh());

        // l'enfant n'est pas perdu : il passe sous le doublon
        $this->assertSame($doublon->id, $enfant->fresh()->parent_id);
    }

    public function test_accepter_conserve_les_deux_et_leve_le_marquage(): void
    {
        [$original, $doublon] = $this->couple();

        $this->assertTrue((new ConflitService())->accepter($doublon->fresh()));

        $this->assertDatabaseHas('items', ['id' => $original->id]);
        $gagnant = $doublon->fresh();
        $this->assertFalse($gagnant->en_conflit);
        $this->assertNull($gagnant->conflit_de);
    }

    public function test_resolution_noop_si_pas_en_conflit(): void
    {
        $item = Item::factory()->create(['en_conflit' => false]);

        $this->assertFalse((new ConflitService())->garder($item));
        $this->assertFalse((new ConflitService())->accepter($item));
    }

    public function test_lever_le_marquage_ne_bump_pas_la_version(): void
    {
        [, $doublon] = $this->couple();
        $versionAvant = $doublon->version;

        (new ConflitService())->accepter($doublon->fresh());

        $this->assertSame($versionAvant, $doublon->fresh()->version);
    }

    public function test_tree_compte_les_conflits(): void
    {
        $this->couple();
        Item::factory()->create(['en_conflit' => false]); // bruit

        Livewire::test(self::TREE)
            ->assertSet('filtreConflits', false)
            ->assertSeeHtml('conflit(s) à résoudre');
    }

    public function test_tree_garder_conflit_resout_et_sort_du_filtre(): void
    {
        [$original, $doublon] = $this->couple();

        Livewire::test(self::TREE)
            ->set('filtreConflits', true)
            ->call('garderConflit', $doublon->id)
            ->assertSet('filtreConflits', false); // plus de conflit → on sort du filtre

        $this->assertDatabaseMissing('items', ['id' => $original->id]);
        $this->assertFalse($doublon->fresh()->en_conflit);
    }

    public function test_tree_accepter_conflit_leve_le_marquage(): void
    {
        [$original, $doublon] = $this->couple();

        Livewire::test(self::TREE)
            ->call('accepterConflit', $doublon->id);

        $this->assertDatabaseHas('items', ['id' => $original->id]);
        $this->assertFalse($doublon->fresh()->en_conflit);
    }
}
