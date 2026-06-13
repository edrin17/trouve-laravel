<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_house_cascades_to_items(): void
    {
        $house = House::factory()->create();
        Item::factory()->count(3)->create(['house_id' => $house->id]);

        $house->delete();

        $this->assertSame(0, Item::count());
    }

    public function test_deleting_item_cascades_to_children(): void
    {
        $parent = Item::factory()->create();
        $child = Item::factory()->childOf($parent)->create();
        Item::factory()->childOf($child)->create(); // petit-enfant

        $parent->delete();

        // toute la branche disparaît
        $this->assertSame(0, Item::count());
    }

    public function test_deleting_item_cascades_to_tag_links(): void
    {
        $item = Item::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $item->tags()->attach($tags->pluck('id'));

        $item->delete();

        // les liens du pivot sont supprimés, mais pas les tags eux-mêmes
        $this->assertSame(0, DB::table('item_tag')->count());
        $this->assertSame(2, Tag::count());
    }

    public function test_deleting_tag_cascades_to_links_not_items(): void
    {
        $item = Item::factory()->create();
        $tag = Tag::factory()->create();
        $item->tags()->attach($tag->id);

        $tag->delete();

        $this->assertSame(0, DB::table('item_tag')->count());
        $this->assertSame(1, Item::count()); // l'item survit
    }
}
