<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Gestion des images d'items (portage de sauvegarder_image / supprimer_image v1).
 *
 * Une image par item. Stockage sur le disque public sous items/.
 * Redimensionnement au grand côté max (comme IMAGE_MAX_SIDE en v1), ré-encodage JPEG.
 */
class ImageService
{
    /** Côté maximum après redimensionnement, en pixels (cf. IMAGE_MAX_SIDE v1). */
    public const MAX_SIDE = 1024;

    /** Dossier de stockage sur le disque public. */
    public const DOSSIER = 'items';

    /** Disque utilisé (public → servi via /storage). */
    public const DISQUE = 'public';

    /**
     * Redimensionne et stocke un fichier uploadé, retourne le nom de fichier généré.
     * Le grand côté ne dépasse pas MAX_SIDE (sans agrandir les petites images).
     */
    public function stocker(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());

        // scaleDown : n'agrandit jamais, préserve le ratio, tient dans la boîte donnée
        $image->scaleDown(self::MAX_SIDE, self::MAX_SIDE);

        $filename = Str::uuid()->toString() . '.jpg';
        $chemin = self::DOSSIER . '/' . $filename;

        Storage::disk(self::DISQUE)->put($chemin, (string) $image->toJpeg(quality: 85));

        return $filename;
    }

    /**
     * Copie un fichier image existant sous un nouveau nom, retourne ce nom.
     * No-op (null) si l'original est null/absent. Utilisé pour les doublons de
     * conflit, qui doivent posséder leur propre fichier (suppression indépendante).
     */
    public function dupliquer(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }
        $source = self::DOSSIER . '/' . $filename;
        if (!Storage::disk(self::DISQUE)->exists($source)) {
            return null;
        }

        $copie = Str::uuid()->toString() . '.jpg';
        Storage::disk(self::DISQUE)->copy($source, self::DOSSIER . '/' . $copie);

        return $copie;
    }

    /** Supprime le fichier image s'il existe (no-op si null/absent). */
    public function supprimer(?string $filename): void
    {
        if (!$filename) {
            return;
        }
        Storage::disk(self::DISQUE)->delete(self::DOSSIER . '/' . $filename);
    }

    /** URL publique de l'image, ou null si pas d'image. */
    public function url(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }
        return Storage::disk(self::DISQUE)->url(self::DOSSIER . '/' . $filename);
    }
}
