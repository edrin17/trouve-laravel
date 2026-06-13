<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes pré-créés (pas d'inscription publique — cercle fermé de 3 personnes).
 *
 * Idempotent : firstOrCreate sur l'email. Le mot de passe par défaut DOIT être
 * changé en production via `php artisan app:creer-utilisateur` (ou en éditant
 * la valeur ci-dessous avant le premier seed). Cf. mémoire projet.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = [
            ['name' => 'Régis',        'email' => 'regis@trouve.local'],
            ['name' => 'Utilisateur 2', 'email' => 'user2@trouve.local'],
            ['name' => 'Utilisateur 3', 'email' => 'user3@trouve.local'],
        ];

        foreach ($comptes as $compte) {
            User::firstOrCreate(
                ['email' => $compte['email']],
                ['name' => $compte['name'], 'password' => Hash::make('password')],
            );
        }
    }
}
