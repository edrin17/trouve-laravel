<?php

namespace Tests\Feature;

use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ImageServiceTest extends TestCase
{
    private ImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ImageService::DISQUE);
        $this->service = new ImageService();
    }

    public function test_stocke_le_fichier_sur_le_disque(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 600, 400);

        $filename = $this->service->stocker($file);

        $this->assertStringEndsWith('.jpg', $filename);
        Storage::disk(ImageService::DISQUE)->assertExists(ImageService::DOSSIER . '/' . $filename);
    }

    public function test_redimensionne_les_images_trop_grandes(): void
    {
        $file = UploadedFile::fake()->image('grande.jpg', 3000, 2000);

        $filename = $this->service->stocker($file);

        $contenu = Storage::disk(ImageService::DISQUE)->get(ImageService::DOSSIER . '/' . $filename);
        $image = (new ImageManager(new Driver()))->read($contenu);

        // grand côté ramené à MAX_SIDE, ratio préservé
        $this->assertSame(ImageService::MAX_SIDE, $image->width());
        $this->assertSame((int) round(ImageService::MAX_SIDE * 2000 / 3000), $image->height());
    }

    public function test_n_agrandit_pas_les_petites_images(): void
    {
        $file = UploadedFile::fake()->image('petite.jpg', 200, 150);

        $filename = $this->service->stocker($file);

        $contenu = Storage::disk(ImageService::DISQUE)->get(ImageService::DOSSIER . '/' . $filename);
        $image = (new ImageManager(new Driver()))->read($contenu);

        $this->assertSame(200, $image->width());
        $this->assertSame(150, $image->height());
    }

    public function test_supprime_le_fichier(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');
        $filename = $this->service->stocker($file);

        $this->service->supprimer($filename);

        Storage::disk(ImageService::DISQUE)->assertMissing(ImageService::DOSSIER . '/' . $filename);
    }

    public function test_supprimer_null_ne_fait_rien(): void
    {
        $this->service->supprimer(null);

        $this->assertTrue(true); // aucune exception
    }

    public function test_url_null_si_pas_de_fichier(): void
    {
        $this->assertNull($this->service->url(null));
    }
}
