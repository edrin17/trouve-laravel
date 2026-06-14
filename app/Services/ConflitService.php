<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Résolution des conflits matérialisés (politique « garder les deux »).
 * Cf. docs/conception_mode_hors_connexion.md §5-6.
 *
 * Un objet en conflit est un doublon (en_conflit=true, conflit_de=<uuid de
 * l'original officiel>) créé par SyncService. L'utilisateur tranche, en voyant
 * les deux versions côte à côte (cf. tree::comparaisons) :
 *  - garder le doublon (ma version) → il prend la place de l'original supprimé,
 *  - garder l'original (version serveur) → on supprime le doublon,
 *  - garder les deux → on lève seulement le marquage.
 * La résolution nettoie le suffixe « (conflit — X) » ajouté à la création.
 */
class ConflitService
{
    public function __construct(private ImageService $images = new ImageService())
    {
    }

    /**
     * « Garder ma version » : le doublon gagne. L'original (uuid = conflit_de)
     * est supprimé après réattachement de ses enfants au doublon — sinon la
     * CASCADE détruirait un sous-arbre que le doublon, simple copie, n'a pas.
     * Le marquage de conflit est ensuite levé et le nom nettoyé sur le gagnant.
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

            $this->resoudre($doublon);
        });

        return true;
    }

    /**
     * « Garder la version serveur » : l'original gagne, le doublon (ma version)
     * est supprimé. Les enfants éventuels du doublon sont réattachés à l'original
     * pour ne rien perdre. No-op si l'item n'est pas en conflit.
     */
    public function garderServeur(Item $doublon): bool
    {
        if (! $doublon->en_conflit) {
            return false;
        }

        DB::transaction(function () use ($doublon) {
            $original = Item::where('uuid', $doublon->conflit_de)->first();

            if ($original) {
                // Réattacher d'éventuels enfants du doublon à l'original gagnant.
                Item::where('parent_id', $doublon->id)->update(['parent_id' => $original->id]);
            }
            // Le doublon disparaît (son image lui est propre → nettoyée).
            $this->images->supprimer($doublon->image_filename);
            $doublon->delete();
        });

        return true;
    }

    /**
     * « Garder les deux » : on conserve l'original ET le doublon, on lève le
     * marquage de conflit sur le doublon (qui devient un objet normal, distinct)
     * et on nettoie son nom. No-op si l'item n'est pas en conflit.
     */
    public function accepter(Item $doublon): bool
    {
        if (! $doublon->en_conflit) {
            return false;
        }

        $this->resoudre($doublon);

        return true;
    }

    /**
     * Finalise la résolution sur le gagnant : lève le marquage et nettoie le
     * suffixe « (conflit — X) ». Sans bumper la version (changement technique —
     * en_conflit/conflit_de/name nettoyé hors logique métier de l'utilisateur ;
     * on écrit en court-circuitant le hook pour ne pas incrémenter).
     */
    private function resoudre(Item $item): void
    {
        $item->forceFill([
            'en_conflit' => false,
            'conflit_de' => null,
            'name'       => $this->nomNettoye($item->name),
        ])->saveQuietly();
    }

    /**
     * Retire le suffixe de conflit « (conflit — Auteur) » ou « (conflit) » ajouté
     * à la création du doublon (cf. SyncService::suffixerConflit).
     */
    public function nomNettoye(string $nom): string
    {
        return trim(preg_replace('/\s*\(conflit(?:\s+—[^)]*)?\)\s*$/u', '', $nom));
    }
}
