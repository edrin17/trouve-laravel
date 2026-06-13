<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration — portage de init_mode_debug() de la v1 Python.
 *
 * Deux maisons, une hiérarchie d'items sur 3 niveaux, quatre tags et
 * leurs associations. Permet de développer l'UI avec du contenu réaliste.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Maisons ──────────────────────────────────
        $maisonA = House::create(['name' => 'Maison A', 'description' => 'Maison de test A']);
        $maisonB = House::create(['name' => 'Maison B', 'description' => 'Maison de test B']);

        // ── Tags ─────────────────────────────────────
        $electronique = Tag::create(['name' => 'électronique']);
        $salon        = Tag::create(['name' => 'salon']);
        $vetement     = Tag::create(['name' => 'vêtement']);
        $rangement    = Tag::create(['name' => 'rangement']);

        // ── Maison A : Salon > Meuble TV > {Télécommande, Câble HDMI} ──
        $salonPiece = Item::create([
            'name' => 'Salon', 'house_id' => $maisonA->id, 'is_container' => true,
        ]);
        $meubleTv = Item::create([
            'name' => 'Meuble TV', 'house_id' => $maisonA->id,
            'parent_id' => $salonPiece->id, 'is_container' => true,
        ]);
        $telecommande = Item::create([
            'name' => 'Télécommande', 'house_id' => $maisonA->id,
            'parent_id' => $meubleTv->id, 'quantity' => 1, 'is_container' => false,
        ]);
        $cableHdmi = Item::create([
            'name' => 'Câble HDMI', 'house_id' => $maisonA->id,
            'parent_id' => $meubleTv->id, 'quantity' => 2, 'is_container' => false,
        ]);
        Item::create([
            'name' => 'Cuisine', 'house_id' => $maisonA->id, 'is_container' => true,
        ]);

        // ── Maison B : Chambre > Placard > {Pull bleu, Veste rouge} ──
        $chambre = Item::create([
            'name' => 'Chambre', 'house_id' => $maisonB->id, 'is_container' => true,
        ]);
        $placard = Item::create([
            'name' => 'Placard', 'house_id' => $maisonB->id,
            'parent_id' => $chambre->id, 'is_container' => true,
        ]);
        $pullBleu = Item::create([
            'name' => 'Pull bleu', 'house_id' => $maisonB->id,
            'parent_id' => $placard->id, 'quantity' => 1, 'is_container' => false,
        ]);
        $vesteRouge = Item::create([
            'name' => 'Veste rouge', 'house_id' => $maisonB->id,
            'parent_id' => $placard->id, 'quantity' => 1, 'is_container' => false,
        ]);

        // ── Associations de tags (many-to-many) ──────
        $meubleTv->tags()->attach([$electronique->id, $salon->id]);
        $telecommande->tags()->attach($electronique->id);
        $cableHdmi->tags()->attach($electronique->id);
        $placard->tags()->attach($rangement->id);
        $pullBleu->tags()->attach($vetement->id);
        $vesteRouge->tags()->attach($vetement->id);
    }
}
