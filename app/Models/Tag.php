<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    /** Champs assignables en masse. */
    protected $fillable = [
        'name',
    ];

    /** Items portant ce tag (many-to-many via item_tag). */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class);
    }
}
