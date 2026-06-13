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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();

            // Maison de rattachement — suppression en cascade
            $table->foreignId('house_id')
                ->constrained('houses')
                ->cascadeOnDelete();

            // Parent hiérarchique (auto-référence) — racine si null
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('items')
                ->cascadeOnDelete();

            $table->boolean('is_container')->default(true);
            $table->string('image_filename')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
