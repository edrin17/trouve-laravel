<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Journal d'idempotence : chaque op_id traité y est mémorisé avec son
        // résultat, pour qu'un rejeu (réponse réseau perdue) renvoie le même
        // résultat sans ré-appliquer l'opération. Cf. docs/conception §3.2.
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('op_id')->unique();
            $table->string('type');
            $table->json('resultat'); // statut + version + id renvoyés au client
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
