<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_name(): void
    {
        Item::factory()->create(['name' => 'Pull bleu']);
        Item::factory()->create(['name' => 'Veste rouge']);

        $results = Item::search('pull')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Pull bleu', $results->first()->name);
    }

    public function test_search_matches_description(): void
    {
        Item::factory()->create(['name' => 'Boîte', 'description' => 'contient des outils']);
        Item::factory()->create(['name' => 'Carton', 'description' => 'vide']);

        $results = Item::search('outils')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Boîte', $results->first()->name);
    }

    public function test_search_matches_tag(): void
    {
        $item = Item::factory()->create(['name' => 'Marteau']);
        $tag = Tag::factory()->create(['name' => 'bricolage']);
        $item->tags()->attach($tag->id);

        Item::factory()->create(['name' => 'Cuillère']);

        $results = Item::search('bricolage')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Marteau', $results->first()->name);
    }

    public function test_search_is_case_insensitive_ascii(): void
    {
        Item::factory()->create(['name' => 'Tournevis']);

        $this->assertCount(1, Item::search('TOURNEVIS')->get());
        $this->assertCount(1, Item::search('tournevis')->get());
    }

    public function test_search_does_not_duplicate_with_multiple_tags(): void
    {
        $item = Item::factory()->create(['name' => 'Perceuse']);
        $item->tags()->attach([
            Tag::factory()->create(['name' => 'bricolage'])->id,
            Tag::factory()->create(['name' => 'electrique'])->id,
        ]);

        // un seul résultat malgré 2 tags correspondants potentiels
        $results = Item::search('perceuse')->get();
        $this->assertCount(1, $results);
    }

    public function test_search_can_be_scoped_to_house(): void
    {
        $maisonA = House::factory()->create();
        $maisonB = House::factory()->create();
        Item::factory()->create(['name' => 'Lampe', 'house_id' => $maisonA->id]);
        Item::factory()->create(['name' => 'Lampe', 'house_id' => $maisonB->id]);

        $results = Item::where('house_id', $maisonA->id)->search('lampe')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->house->is($maisonA));
    }

    public function test_empty_search_returns_all(): void
    {
        Item::factory()->count(3)->create();

        $this->assertCount(3, Item::search('')->get());
        $this->assertCount(3, Item::search('   ')->get());
        $this->assertCount(3, Item::search(null)->get());
    }
}
