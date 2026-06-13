<?php

namespace App\Services;

use App\Models\House;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisation hors-ligne — versioning optimiste et application des
 * opérations du journal client. Cf. docs/conception_mode_hors_connexion.md §4.
 *
 * ÉTAPE 2 : versioning, idempotence, détection de conflit (signalée, PAS encore
 * matérialisée par duplication — ce sera l'étape 3). « delete-vs-update = la
 * modif gagne » est en revanche déjà appliqué dès maintenant.
 */
class SyncService
{
    public const APPLIQUE = 'applique';
    public const CONFLIT  = 'conflit';
    public const IGNORE   = 'ignore'; // idempotent / sans effet

    /** Modèles versionnés, indexés par nom d'entité. @var array<string,class-string> */
    private const ENTITES = [
        'item'  => Item::class,
        'house' => House::class,
    ];

    /**
     * Applique une liste d'opérations (FIFO) et renvoie un résultat par op.
     *
     * @param  array<array<string,mixed>>  $operations
     * @return array<array<string,mixed>>
     */
    public function push(array $operations): array
    {
        $resultats = [];
        foreach ($operations as $op) {
            $resultats[] = $this->appliquerOperation($op);
        }
        return $resultats;
    }

    /**
     * Applique une opération unique, de façon idempotente et transactionnelle.
     *
     * @param  array<string,mixed>  $op  { op_id, type, entite, uuid, base_version, payload }
     * @return array<string,mixed>       { op_id, statut, version?, id?, uuid? }
     */
    public function appliquerOperation(array $op): array
    {
        $opId = $op['op_id'];

        // Idempotence : op déjà traitée → on renvoie le résultat mémorisé.
        $deja = DB::table('sync_operations')->where('op_id', $opId)->first();
        if ($deja) {
            return json_decode($deja->resultat, true);
        }

        $resultat = DB::transaction(function () use ($op) {
            return match ($op['type']) {
                'create' => $this->create($op),
                'delete' => $this->delete($op),
                default  => $this->update($op), // update / move / tag / image
            };
        });

        $resultat['op_id'] = $opId;

        DB::table('sync_operations')->insert([
            'op_id'     => $opId,
            'type'      => $op['type'],
            'resultat'  => json_encode($resultat),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $resultat;
    }

    /** Crée un objet par son uuid (ou idempotent si l'uuid existe déjà). */
    private function create(array $op): array
    {
        $modele = $this->modele($op['entite']);
        $existant = $modele::where('uuid', $op['uuid'])->first();
        if ($existant) {
            // Déjà créé (rejeu ou autre client) → idempotent.
            return ['statut' => self::IGNORE, 'id' => $existant->id, 'uuid' => $existant->uuid, 'version' => $existant->version];
        }

        $objet = $modele::create($op['payload'] + ['uuid' => $op['uuid']]);

        return ['statut' => self::APPLIQUE, 'id' => $objet->id, 'uuid' => $objet->uuid, 'version' => $objet->version];
    }

    /** Applique une modification (update/move/tag/image) avec détection optimiste. */
    private function update(array $op): array
    {
        $modele = $this->modele($op['entite']);
        $objet = $modele::where('uuid', $op['uuid'])->first();

        // update-vs-delete : l'objet a été supprimé entretemps → conflit (étape 3 : recréera).
        if (! $objet) {
            return ['statut' => self::CONFLIT, 'raison' => 'update-vs-delete', 'uuid' => $op['uuid']];
        }

        // Version périmée → conflit concurrent (étape 3 : dupliquera).
        if ((int) $objet->version !== (int) $op['base_version']) {
            return ['statut' => self::CONFLIT, 'raison' => 'update-vs-update', 'uuid' => $op['uuid'], 'version' => $objet->version];
        }

        $objet->update($op['payload']); // le hook updating incrémente version

        return ['statut' => self::APPLIQUE, 'id' => $objet->id, 'uuid' => $objet->uuid, 'version' => $objet->fresh()->version];
    }

    /** Supprime un objet — « delete-vs-update = la modif gagne ». */
    private function delete(array $op): array
    {
        $modele = $this->modele($op['entite']);
        $objet = $modele::where('uuid', $op['uuid'])->first();

        // Déjà supprimé → idempotent.
        if (! $objet) {
            return ['statut' => self::IGNORE, 'uuid' => $op['uuid']];
        }

        // delete-vs-update : un autre a modifié l'objet entretemps → la modif gagne,
        // la suppression est ANNULÉE et signalée (décision validée).
        if ((int) $objet->version !== (int) $op['base_version']) {
            return ['statut' => self::CONFLIT, 'raison' => 'delete-vs-update', 'uuid' => $op['uuid'], 'version' => $objet->version];
        }

        $objet->delete();

        return ['statut' => self::APPLIQUE, 'uuid' => $op['uuid']];
    }

    /**
     * Full pull : renvoie l'état partagé complet + un curseur serveur.
     * (L'incrémental via $depuis est une optimisation ultérieure — cf. doc §4.2.)
     *
     * @return array<string,mixed>
     */
    public function pull(?string $depuis = null): array
    {
        return [
            'curseur' => now()->toIso8601String(),
            'houses'  => House::orderBy('id')->get(),
            'items'   => Item::with('tags:id,name')->orderBy('id')->get(),
        ];
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function modele(string $entite): string
    {
        return self::ENTITES[$entite] ?? throw new \InvalidArgumentException("Entité inconnue : {$entite}");
    }
}
