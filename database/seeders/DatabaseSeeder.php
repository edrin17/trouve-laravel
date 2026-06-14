<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // ⚠️ NE PAS ajouter WithoutModelEvents : les hooks `creating` d'Item/House
    // génèrent l'uuid et la version initiale dont dépend toute la couche de
    // synchronisation hors-ligne. Muter les events sèmerait des lignes à
    // uuid/version NULL (conflits non rattachables, invariant sync rompu).

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
