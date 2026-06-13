<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreerUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_un_utilisateur(): void
    {
        $this->artisan('app:creer-utilisateur', ['name' => 'Alice', 'email' => 'alice@trouve.local'])
            ->expectsQuestion('Mot de passe', 'motdepasse-1')
            ->expectsOutputToContain('créé')
            ->assertSuccessful();

        $user = User::where('email', 'alice@trouve.local')->first();
        $this->assertNotNull($user);
        $this->assertSame('Alice', $user->name);
        $this->assertTrue(Hash::check('motdepasse-1', $user->password));
    }

    public function test_met_a_jour_le_mot_de_passe_d_un_compte_existant(): void
    {
        $user = User::factory()->create([
            'email'    => 'bob@trouve.local',
            'password' => Hash::make('ancien-mot-de-passe'),
        ]);

        $this->artisan('app:creer-utilisateur', ['name' => 'Bob', 'email' => 'bob@trouve.local'])
            ->expectsQuestion('Mot de passe', 'nouveau-mot-de-passe')
            ->expectsOutputToContain('mis à jour')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->fresh()->password));
        $this->assertSame(1, User::where('email', 'bob@trouve.local')->count());
    }

    public function test_email_invalide_refuse(): void
    {
        // l'email est validé avant la saisie du mot de passe → pas de prompt
        $this->artisan('app:creer-utilisateur', ['name' => 'Bad', 'email' => 'pas-un-email'])
            ->assertFailed();

        $this->assertSame(0, User::where('name', 'Bad')->count());
    }
}
