<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Logique métier des items touchant à la hiérarchie :
 * déplacement dans l'arbre et entre maisons (portage de
 * deplacer_objet / deplacer_objet_vers_maison de la v1).
 */
class ItemService
{
    /**
     * Déplace un item sous un nouveau parent dans la même maison.
     * $nouveauParentId = null → item placé à la racine de sa maison.
     */
    public function deplacer(Item $item, ?int $nouveauParentId): void
    {
        $item->update(['parent_id' => $nouveauParentId]);
    }

    /**
     * Déplace un item et tous ses descendants vers une autre maison.
     * Seul l'item racine change de parent ; le reste de l'arbre garde
     * sa structure interne mais bascule de house_id.
     */
    public function deplacerVersMaison(Item $item, int $nouvelleMaisonId, ?int $nouveauParentId = null): void
    {
        DB::transaction(function () use ($item, $nouvelleMaisonId, $nouveauParentId) {
            $idsSousArbre = $this->idsDescendants($item);

            // Tout le sous-arbre change de maison
            Item::whereIn('id', $idsSousArbre)->update(['house_id' => $nouvelleMaisonId]);

            // Seule la racine déplacée change de parent
            $item->update(['parent_id' => $nouveauParentId]);
        });
    }

    /**
     * IDs de l'item et de toute sa descendance (parcours en largeur).
     *
     * @return array<int>
     */
    public function idsDescendants(Item $item): array
    {
        $ids = [$item->id];
        $aTraiter = [$item->id];

        while ($aTraiter) {
            $enfants = Item::whereIn('parent_id', $aTraiter)->pluck('id')->all();
            $aTraiter = $enfants;
            $ids = array_merge($ids, $enfants);
        }

        return $ids;
    }

    /**
     * Vrai si $candidat est $item lui-même ou l'un de ses descendants.
     * Empêche de déplacer un item dans son propre sous-arbre.
     */
    public function estDescendant(Item $item, Item $candidat): bool
    {
        return in_array($candidat->id, $this->idsDescendants($item), true);
    }
}
