<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // ── Images de démo (portage de _generer_images_debug v1) ──
        $this->imageDemo($telecommande, [52, 152, 219]);  // bleu
        $this->imageDemo($pullBleu, [41, 128, 185]);       // bleu foncé
        $this->imageDemo($vesteRouge, [192, 57, 43]);      // rouge
    }

    /**
     * Génère une image PNG unie via GD et l'assigne à un item (via le disque
     * public, comme un upload réel). Sans dépendance externe.
     *
     * @param  array{int,int,int}  $rgb
     */
    private function imageDemo(Item $item, array $rgb): void
    {
        $img = imagecreatetruecolor(400, 300);
        $couleur = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($img, 0, 0, $couleur);
        $blanc = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 20, 20, $item->name, $blanc);

        ob_start();
        imagejpeg($img, null, 85);
        $contenu = ob_get_clean();
        imagedestroy($img);

        $filename = Str::uuid()->toString() . '.jpg';
        Storage::disk(ImageService::DISQUE)->put(ImageService::DOSSIER . '/' . $filename, $contenu);
        $item->update(['image_filename' => $filename]);
    }
}
