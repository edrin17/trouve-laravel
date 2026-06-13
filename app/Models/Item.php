<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    /** Champs assignables en masse. */
    protected $fillable = [
        'name',
        'description',
        'quantity',
        'unit',
        'house_id',
        'parent_id',
        'is_container',
        'image_filename',
    ];

    /** Conversions de types automatiques. */
    protected $casts = [
        'quantity'     => 'decimal:2',
        'is_container' => 'boolean',
    ];

    /** Maison de rattachement. */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /** Item parent dans la hiérarchie (null si racine). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_id');
    }

    /** Items contenus directement dans cet item. */
    public function children(): HasMany
    {
        return $this->hasMany(Item::class, 'parent_id');
    }

    /** Tags associés à cet item (many-to-many via item_tag). */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Recherche par mots-clés dans le nom, la description et les tags.
     * Insensible à la casse. Combinable avec ->where('house_id', …).
     */
    #[Scope]
    protected function search(Builder $query, ?string $terme): Builder
    {
        $terme = trim((string) $terme);
        if ($terme === '') {
            return $query;
        }
        $like = '%' . $terme . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like));
        });
    }
}
