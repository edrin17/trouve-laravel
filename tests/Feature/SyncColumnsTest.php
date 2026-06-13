<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Colonnes de synchronisation hors-ligne (uuid/version/en_conflit/conflit_de).
 * Cf. docs/conception_mode_hors_connexion.md, étape 1.
 */
class SyncColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_item_recoit_un_uuid_a_la_creation(): void
    {
        $item = Item::factory()->create();

        $this->assertNotEmpty($item->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $item->uuid,
        );
    }

    public function test_une_maison_recoit_un_uuid_a_la_creation(): void
    {
        $house = House::factory()->create();

        $this->assertNotEmpty($house->uuid);
    }

    public function test_l_uuid_fourni_est_respecte(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';
        $item = Item::factory()->create(['uuid' => $uuid]);

        $this->assertSame($uuid, $item->fresh()->uuid);
    }

    public function test_les_uuid_sont_uniques(): void
    {
        $a = Item::factory()->create();
        $b = Item::factory()->create();

        $this->assertNotSame($a->uuid, $b->uuid);
    }

    public function test_valeurs_par_defaut_des_colonnes_de_sync(): void
    {
        $item = Item::factory()->create();

        $this->assertSame(1, $item->version);
        $this->assertFalse($item->en_conflit);
        $this->assertNull($item->conflit_de);
    }

    public function test_les_casts_sont_appliques(): void
    {
        $item = Item::factory()->create(['version' => 5, 'en_conflit' => 1]);
        $item->refresh();

        $this->assertIsInt($item->version);
        $this->assertIsBool($item->en_conflit);
        $this->assertTrue($item->en_conflit);
    }
}
