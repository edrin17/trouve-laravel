<?php

namespace Tests\Feature\Livewire;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'auth.login';

    public function test_connexion_reussie_redirige_vers_inventory(): void
    {
        $user = User::factory()->create([
            'email'    => 'jean@trouve.local',
            'password' => Hash::make('secret-123'),
        ]);

        Livewire::test(self::COMPONENT)
            ->set('email', 'jean@trouve.local')
            ->set('password', 'secret-123')
            ->call('connexion')
            ->assertHasNoErrors()
            ->assertRedirect('/inventory');

        $this->assertAuthenticatedAs($user);
    }

    public function test_mauvais_mot_de_passe_refuse(): void
    {
        User::factory()->create([
            'email'    => 'jean@trouve.local',
            'password' => Hash::make('secret-123'),
        ]);

        Livewire::test(self::COMPONENT)
            ->set('email', 'jean@trouve.local')
            ->set('password', 'mauvais')
            ->call('connexion')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_champs_obligatoires(): void
    {
        Livewire::test(self::COMPONENT)
            ->set('email', '')
            ->set('password', '')
            ->call('connexion')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_throttling_apres_cinq_echecs(): void
    {
        User::factory()->create([
            'email'    => 'jean@trouve.local',
            'password' => Hash::make('secret-123'),
        ]);

        $composant = Livewire::test(self::COMPONENT)
            ->set('email', 'jean@trouve.local')
            ->set('password', 'mauvais');

        // 5 tentatives consomment le quota
        for ($i = 0; $i < 5; $i++) {
            $composant->call('connexion')->assertHasErrors('email');
        }

        // la 6e est bloquée par le throttle (message différent)
        $composant->call('connexion')
            ->assertHasErrors('email');

        $this->assertTrue(RateLimiter::tooManyAttempts('jean@trouve.local|127.0.0.1', 5));
    }
}
