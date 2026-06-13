<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class House extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // uuid stable + version initiale garantis dès la création (cf. sync hors-ligne)
        static::creating(function (House $house) {
            if (empty($house->uuid)) {
                $house->uuid = (string) Str::uuid();
            }
            if ($house->version === null) {
                $house->version = 1;
            }
            if ($house->en_conflit === null) {
                $house->en_conflit = false;
            }
        });

        // Verrouillage optimiste : toute modification métier réelle incrémente version.
        static::updating(function (House $house) {
            if ($house->isDirty(self::CHAMPS_VERSIONNES)) {
                $house->version = (int) $house->getOriginal('version') + 1;
            }
        });
    }

    /** Champs métier dont la modification incrémente la version. */
    private const CHAMPS_VERSIONNES = ['name', 'description'];

    /** Champs assignables en masse. */
    protected $fillable = [
        'name',
        'description',
        'uuid',
        'version',
        'en_conflit',
        'conflit_de',
    ];

    /** Conversions de types automatiques. */
    protected $casts = [
        'version'    => 'integer',
        'en_conflit' => 'boolean',
    ];

    /** Tous les items rattachés à cette maison (à plat, tous niveaux). */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** Items racines de la maison (sans parent). */
    public function rootItems(): HasMany
    {
        return $this->hasMany(Item::class)->whereNull('parent_id');
    }
}
