<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logique métier des tags ne tenant pas sur un seul modèle :
 * édition par lot sur plusieurs items (portage de modifier_tags_groupe v1).
 */
class TagService
{
    /**
     * Ajoute et/ou retire des tags sur un groupe d'items.
     *
     * @param  array<int>     $itemIds    items concernés
     * @param  array<string>  $aAjouter   noms de tags à ajouter (créés si absents)
     * @param  array<string>  $aRetirer   noms de tags à retirer
     */
    public function modifierParLot(array $itemIds, array $aAjouter, array $aRetirer): void
    {
        if (empty($itemIds)) {
            return;
        }

        DB::transaction(function () use ($itemIds, $aAjouter, $aRetirer) {
            $items = Item::whereIn('id', $itemIds)->get();

            // Tags à ajouter : créés à la volée si absents du vocabulaire
            $idsAjouter = collect($aAjouter)
                ->map(fn (string $nom) => Tag::firstOrCreate(['name' => trim($nom)])->id)
                ->all();

            // Tags à retirer : seulement ceux qui existent déjà
            $idsRetirer = Tag::whereIn('name', array_map('trim', $aRetirer))
                ->pluck('id')
                ->all();

            foreach ($items as $item) {
                if ($idsAjouter) {
                    // syncWithoutDetaching = ajoute sans toucher aux tags existants
                    $item->tags()->syncWithoutDetaching($idsAjouter);
                }
                if ($idsRetirer) {
                    $item->tags()->detach($idsRetirer);
                }
            }
        });
    }

    /**
     * Vocabulaire complet avec compteur d'items (portage de lister_tous_tags v1).
     *
     * @return Collection<int, Tag>  chaque Tag a un attribut items_count
     */
    public function vocabulaireAvecCompteur(): Collection
    {
        return Tag::withCount('items')->orderBy('name')->get();
    }
}
