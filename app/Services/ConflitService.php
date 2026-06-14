<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Résolution des conflits matérialisés (politique « garder les deux »).
 * Cf. docs/conception_mode_hors_connexion.md §5-6.
 *
 * Un objet en conflit est un doublon (en_conflit=true, conflit_de=<uuid de
 * l'original officiel>) créé par SyncService. L'utilisateur tranche :
 *  - garder le doublon → il prend la place de l'original (qui est supprimé),
 *  - garder les deux   → on lève seulement le marquage.
 */
class ConflitService
{
    public function __construct(private ImageService $images = new ImageService())
    {
    }

    /**
     * « Garder cette version » : le doublon gagne. L'original (uuid = conflit_de)
     * est supprimé après réattachement de ses enfants au doublon — sinon la
     * CASCADE détruirait un sous-arbre que le doublon, simple copie, n'a pas.
     * Le marquage de conflit est ensuite levé sur le gagnant.
     *
     * No-op si l'item n'est pas en conflit. Renvoie true si une résolution a eu lieu.
     */
    public function garder(Item $doublon): bool
    {
        if (! $doublon->en_conflit) {
            return false;
        }

        DB::transaction(function () use ($doublon) {
            $original = Item::where('uuid', $doublon->conflit_de)->first();

            if ($original) {
                // Les enfants de l'original passent sous le doublon (ne rien perdre).
                Item::where('parent_id', $original->id)->update(['parent_id' => $doublon->id]);
                // L'image de l'original n'est plus référencée → on la nettoie.
                $this->images->supprimer($original->image_filename);
                $original->delete();
            }

            $this->leverMarquage($doublon);
        });

        return true;
    }

    /**
     * « Garder les deux » : on conserve l'original ET le doublon, on lève
     * simplement le marquage de conflit sur le doublon (qui devient un objet
     * normal, distinct). No-op si l'item n'est pas en conflit.
     */
    public function accepter(Item $doublon): bool
    {
        if (! $doublon->en_conflit) {
            return false;
        }

        $this->leverMarquage($doublon);

        return true;
    }

    /**
     * Lève le marquage de conflit sans bumper la version (changement technique,
     * pas une modification métier — les colonnes en_conflit/conflit_de ne sont
     * pas dans CHAMPS_VERSIONNES, donc le hook updating ne déclenche pas).
     */
    private function leverMarquage(Item $item): void
    {
        $item->update(['en_conflit' => false, 'conflit_de' => null]);
    }
}
