<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class House extends Model
{
    /** Champs assignables en masse. */
    protected $fillable = [
        'name',
        'description',
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
