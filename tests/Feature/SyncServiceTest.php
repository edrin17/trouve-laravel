<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Algorithme de synchronisation — étape 2 (versioning + détection optimiste).
 * Cf. docs/conception_mode_hors_connexion.md §4-5.
 */
class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sync = new SyncService();
    }

    /** @return array<string,mixed> */
    private function op(array $surcharge): array
    {
        return array_merge([
            'op_id'        => (string) Str::uuid(),
            'type'         => 'update',
            'entite'       => 'item',
            'uuid'         => (string) Str::uuid(),
            'base_version' => 1,
            'payload'      => [],
        ], $surcharge);
    }

    public function test_create_cree_l_objet_par_uuid(): void
    {
        $house = House::factory()->create();
        $uuid = (string) Str::uuid();

        $r = $this->sync->appliquerOperation($this->op([
            'type'    => 'create',
            'uuid'    => $uuid,
            'payload' => ['name' => 'Nouveau', 'house_id' => $house->id, 'is_container' => true],
        ]));

        $this->assertSame(SyncService::APPLIQUE, $r['statut']);
        $this->assertDatabaseHas('items', ['uuid' => $uuid, 'name' => 'Nouveau', 'version' => 1]);
    }

    public function test_operation_idempotente_par_op_id(): void
    {
        $house = House::factory()->create();
        $op = $this->op([
            'type'    => 'create',
            'uuid'    => (string) Str::uuid(),
            'payload' => ['name' => 'Unique', 'house_id' => $house->id],
        ]);

        $this->sync->appliquerOperation($op);
        $this->sync->appliquerOperation($op); // rejeu

        $this->assertSame(1, Item::where('name', 'Unique')->count());
    }

    public function test_update_version_concordante_applique_et_incremente(): void
    {
        $item = Item::factory()->create(['name' => 'Avant', 'version' => 1]);

        $r = $this->sync->appliquerOperation($this->op([
            'uuid'         => $item->uuid,
            'base_version' => 1,
            'payload'      => ['name' => 'Après'],
        ]));

        $this->assertSame(SyncService::APPLIQUE, $r['statut']);
        $this->assertSame(2, $r['version']);
        $this->assertSame('Après', $item->fresh()->name);
    }

    public function test_update_version_perimee_est_un_conflit(): void
    {
        $item = Item::factory()->create(['name' => 'Officiel', 'version' => 3]);

        $r = $this->sync->appliquerOperation($this->op([
            'uuid'         => $item->uuid,
            'base_version' => 1, // périmé : le serveur est à la v3
            'payload'      => ['name' => 'Local'],
        ]));

        $this->assertSame(SyncService::CONFLIT, $r['statut']);
        $this->assertSame('update-vs-update', $r['raison']);
        // l'objet officiel n'est pas écrasé
        $this->assertSame('Officiel', $item->fresh()->name);
    }

    public function test_update_d_un_objet_supprime_est_un_conflit(): void
    {
        $uuid = (string) Str::uuid();

        $r = $this->sync->appliquerOperation($this->op([
            'uuid'         => $uuid, // n'existe pas
            'base_version' => 1,
            'payload'      => ['name' => 'X'],
        ]));

        $this->assertSame(SyncService::CONFLIT, $r['statut']);
        $this->assertSame('update-vs-delete', $r['raison']);
    }

    public function test_delete_version_concordante_supprime(): void
    {
        $item = Item::factory()->create(['version' => 1]);

        $r = $this->sync->appliquerOperation($this->op([
            'type'         => 'delete',
            'uuid'         => $item->uuid,
            'base_version' => 1,
        ]));

        $this->assertSame(SyncService::APPLIQUE, $r['statut']);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_delete_vs_update_la_modif_gagne(): void
    {
        // un autre client a modifié l'objet (v3), ma suppression se base sur v1
        $item = Item::factory()->create(['version' => 3]);

        $r = $this->sync->appliquerOperation($this->op([
            'type'         => 'delete',
            'uuid'         => $item->uuid,
            'base_version' => 1,
        ]));

        $this->assertSame(SyncService::CONFLIT, $r['statut']);
        $this->assertSame('delete-vs-update', $r['raison']);
        // la suppression est annulée : l'objet survit
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_delete_idempotent_si_deja_supprime(): void
    {
        $uuid = (string) Str::uuid();

        $r = $this->sync->appliquerOperation($this->op([
            'type'         => 'delete',
            'uuid'         => $uuid,
            'base_version' => 1,
        ]));

        $this->assertSame(SyncService::IGNORE, $r['statut']);
    }

    public function test_pull_renvoie_l_etat_et_un_curseur(): void
    {
        $house = House::factory()->create();
        Item::factory()->create(['house_id' => $house->id]); // pas de house en plus

        $etat = $this->sync->pull();

        $this->assertArrayHasKey('curseur', $etat);
        $this->assertCount(1, $etat['houses']);
        $this->assertCount(1, $etat['items']);
    }
}
