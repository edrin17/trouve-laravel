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

    /**
     * Renomme un tag du vocabulaire (portage de renommer_tag v1, avec fusion).
     *
     * Le nom est normalisé (trim + minuscule). Si un autre tag porte déjà ce
     * nom, les deux sont fusionnés : les items du tag renommé sont rattachés au
     * tag cible, puis le tag source est supprimé. Sinon simple renommage.
     */
    public function renommer(int $tagId, string $nouveauNom): void
    {
        $nouveauNom = trim(mb_strtolower($nouveauNom));
        if ($nouveauNom === '') {
            return;
        }

        DB::transaction(function () use ($tagId, $nouveauNom) {
            $tag = Tag::find($tagId);
            if (!$tag || $tag->name === $nouveauNom) {
                return;
            }

            $cible = Tag::where('name', $nouveauNom)->first();
            if ($cible) {
                // Fusion : transférer les items puis supprimer le tag source
                $itemIds = $tag->items()->pluck('items.id')->all();
                $cible->items()->syncWithoutDetaching($itemIds);
                $tag->delete();
            } else {
                $tag->update(['name' => $nouveauNom]);
            }
        });
    }

    /**
     * Supprime un tag du vocabulaire (portage de supprimer_tag v1).
     * Les associations pivot partent en cascade (FK).
     */
    public function supprimerDuVocabulaire(int $tagId): void
    {
        Tag::whereKey($tagId)->delete();
    }
}
