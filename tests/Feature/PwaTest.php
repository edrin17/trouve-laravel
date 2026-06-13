<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    // Les fichiers statiques (manifeste, sw, icônes) sont servis directement
    // depuis public/ par le serveur web, pas par le routeur Laravel : on valide
    // donc leur présence et leur contenu sur le disque.

    public function test_le_manifeste_existe_et_est_valide(): void
    {
        $chemin = public_path('manifest.json');
        $this->assertFileExists($chemin);

        $manifeste = json_decode(file_get_contents($chemin), true);
        $this->assertSame('Trouve — Inventaire', $manifeste['name']);
        $this->assertSame('standalone', $manifeste['display']);
        $this->assertSame('/', $manifeste['start_url']);
        $this->assertFalse($manifeste['prefer_related_applications']);

        // icônes requises pour l'installabilité Chrome : 192 et 512
        $tailles = array_column($manifeste['icons'], 'sizes');
        $this->assertContains('192x192', $tailles);
        $this->assertContains('512x512', $tailles);
    }

    public function test_le_service_worker_existe(): void
    {
        $chemin = public_path('sw.js');
        $this->assertFileExists($chemin);
        // ne met pas en cache Livewire (sinon casse la réactivité)
        $this->assertStringContainsString('/livewire', file_get_contents($chemin));
    }

    public function test_les_icones_existent(): void
    {
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
    }

    public function test_la_page_offline_est_accessible_sans_authentification(): void
    {
        // doit fonctionner hors session (servie quand le réseau est coupé)
        $this->get('/offline')
            ->assertOk()
            ->assertSee('Hors connexion');
    }

    public function test_le_layout_reference_le_manifeste_et_le_service_worker(): void
    {
        $reponse = $this->actingAs(User::factory()->create())->get('/inventory');
        $reponse->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/sw.js', false);
    }
}
