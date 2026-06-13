<?php

namespace Database\Factories;

use App\Models\House;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'         => fake()->words(2, true),
            'description'  => fake()->optional()->sentence(),
            'quantity'     => fake()->optional()->randomFloat(2, 1, 100),
            'unit'         => fake()->optional()->randomElement(['pcs', 'kg', 'L', 'm']),
            'house_id'     => House::factory(),
            'parent_id'    => null,
            'is_container' => true,
            'image_filename' => null,
        ];
    }

    /** Item feuille (non-conteneur). */
    public function leaf(): static
    {
        return $this->state(fn () => ['is_container' => false]);
    }

    /** Item enfant d'un parent donné (hérite de sa maison). */
    public function childOf(Item $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'house_id'  => $parent->house_id,
        ]);
    }
}
