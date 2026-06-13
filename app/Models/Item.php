<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    /**
     * Supprime le fichier image quand l'item est détruit via le modèle.
     * (Les suppressions en cascade DB ne déclenchent pas cet event — la couche
     * appelante doit alors nettoyer les fichiers du sous-arbre explicitement.)
     */
    protected static function booted(): void
    {
        static::deleting(function (Item $item) {
            (new ImageService())->supprimer($item->image_filename);
        });
    }

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

    /** URL publique de l'image (null si aucune). */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => (new ImageService())->url($this->image_filename));
    }

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

    /** Sous-arbre complet : enfants + tags, récursivement (pour l'affichage). */
    public function descendants(): HasMany
    {
        return $this->children()->with('tags', 'descendants');
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
