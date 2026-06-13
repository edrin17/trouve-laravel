<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Endpoints HTTP de synchronisation. Cf. docs/conception §7.
 */
class SyncEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_exige_l_authentification(): void
    {
        $this->postJson('/sync/push', ['operations' => []])->assertUnauthorized();
    }

    public function test_pull_exige_l_authentification(): void
    {
        // API JSON : 401 plutôt qu'une redirection vers le login
        $this->getJson('/sync/pull')->assertUnauthorized();
    }

    public function test_push_applique_les_operations(): void
    {
        $house = House::factory()->create();

        $payload = ['operations' => [[
            'op_id'        => (string) Str::uuid(),
            'type'         => 'create',
            'entite'       => 'item',
            'uuid'         => (string) Str::uuid(),
            'base_version' => null,
            'payload'      => ['name' => 'Via API', 'house_id' => $house->id, 'is_container' => true],
        ]]];

        $this->actingAs(User::factory()->create())
            ->postJson('/sync/push', $payload)
            ->assertOk()
            ->assertJsonPath('resultats.0.statut', 'applique');

        $this->assertDatabaseHas('items', ['name' => 'Via API']);
    }

    public function test_push_valide_la_structure_des_operations(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/sync/push', ['operations' => [['type' => 'bidon']]])
            ->assertUnprocessable();
    }

    public function test_pull_renvoie_l_etat(): void
    {
        House::factory()->create();
        Item::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/sync/pull')
            ->assertOk()
            ->assertJsonStructure(['curseur', 'houses', 'items']);
    }
}
