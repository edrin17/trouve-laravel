<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_redirige_les_visiteurs_vers_login(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
    }

    public function test_inventory_accessible_une_fois_connecte(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/inventory')->assertOk();
    }

    public function test_login_redirige_les_utilisateurs_deja_connectes(): void
    {
        $user = User::factory()->create();

        // middleware guest → un utilisateur connecté est redirigé hors du login
        // (vers '/', qui redirige ensuite vers /inventory)
        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }

    public function test_la_racine_redirige_vers_inventory(): void
    {
        $this->get('/')->assertRedirect('/inventory');
    }

    public function test_deconnexion_vide_la_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
