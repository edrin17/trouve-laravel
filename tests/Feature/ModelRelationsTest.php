<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_house_has_many_items(): void
    {
        $house = House::factory()->create();
        Item::factory()->count(3)->create(['house_id' => $house->id]);

        $this->assertCount(3, $house->items);
    }

    public function test_root_items_excludes_children(): void
    {
        $house = House::factory()->create();
        $parent = Item::factory()->create(['house_id' => $house->id]);
        Item::factory()->childOf($parent)->create();

        // 1 racine (parent), l'enfant est exclu
        $this->assertCount(1, $house->rootItems);
        $this->assertTrue($house->rootItems->first()->is($parent));
    }

    public function test_item_belongs_to_house(): void
    {
        $house = House::factory()->create();
        $item = Item::factory()->create(['house_id' => $house->id]);

        $this->assertTrue($item->house->is($house));
    }

    public function test_item_parent_and_children_hierarchy(): void
    {
        $parent = Item::factory()->create();
        $child = Item::factory()->childOf($parent)->create();

        $this->assertTrue($child->parent->is($parent));
        $this->assertCount(1, $parent->children);
        $this->assertTrue($parent->children->first()->is($child));
    }

    public function test_item_belongs_to_many_tags(): void
    {
        $item = Item::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $item->tags()->attach($tags->pluck('id'));

        $this->assertCount(2, $item->fresh()->tags);
    }

    public function test_tag_belongs_to_many_items(): void
    {
        $tag = Tag::factory()->create();
        $items = Item::factory()->count(3)->create();
        $tag->items()->attach($items->pluck('id'));

        $this->assertCount(3, $tag->fresh()->items);
    }

    public function test_quantity_is_cast_to_decimal_string(): void
    {
        $item = Item::factory()->create(['quantity' => 2]);

        // cast decimal:2 → représentation "2.00"
        $this->assertSame('2.00', (string) $item->fresh()->quantity);
    }

    public function test_is_container_is_cast_to_boolean(): void
    {
        $item = Item::factory()->leaf()->create();

        $this->assertIsBool($item->fresh()->is_container);
        $this->assertFalse($item->fresh()->is_container);
    }
}
