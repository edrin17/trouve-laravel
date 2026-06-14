<?php

namespace App\Services;

use App\Models\House;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Synchronisation hors-ligne — versioning optimiste et application des
 * opérations du journal client. Cf. docs/conception_mode_hors_connexion.md §4-5.
 *
 * ÉTAPE 3 : les vrais conflits concurrents sont MATÉRIALISÉS selon la politique
 * « garder les deux » — le serveur duplique la version locale rejetée comme un
 * nouvel objet marqué en_conflit, rattaché au même parent, suffixé « (conflit) ».
 * L'objet officiel reste intact. « delete-vs-update = la modif gagne » et le
 * rejet des moves créant un cycle restent appliqués comme à l'étape 2.
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
     * @param  string|null  $auteur  Nom de l'utilisateur qui pousse (suffixe des
     *                               doublons de conflit). Fourni par le contrôleur
     *                               en session ; null hors contexte authentifié.
     */
    public function __construct(private ?string $auteur = null)
    {
    }

    /** Définit l'auteur du push (suffixe des doublons de conflit) ; fluent. */
    public function pour(?string $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

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

        $objet = $modele::create($this->champs($op['payload']) + ['uuid' => $op['uuid']]);
        $this->synchroniserTags($objet, $op);

        return ['statut' => self::APPLIQUE, 'id' => $objet->id, 'uuid' => $objet->uuid, 'version' => $objet->version];
    }

    /** Applique une modification (update/move/tag/image) avec détection optimiste. */
    private function update(array $op): array
    {
        $modele = $this->modele($op['entite']);
        $objet = $modele::where('uuid', $op['uuid'])->first();

        // update-vs-delete : l'objet a été supprimé entretemps. On matérialise
        // l'intention locale en recréant l'objet, marqué conflit (§5.3). Le parent
        // disparu est résolu à la racine de la maison par materialiserConflit().
        if (! $objet) {
            $doublon = $this->materialiserConflit($op, originalDisparu: true);

            return ['statut' => self::CONFLIT, 'raison' => 'update-vs-delete', 'uuid' => $op['uuid']]
                + $this->refDoublon($doublon);
        }

        // Version périmée → vrai conflit concurrent. L'officiel reste intact ; on
        // duplique la version locale rejetée à côté (§5.1).
        if ((int) $objet->version !== (int) $op['base_version']) {
            $doublon = $this->materialiserConflit($op, originalDisparu: false, original: $objet);

            return ['statut' => self::CONFLIT, 'raison' => 'update-vs-update', 'uuid' => $op['uuid'], 'version' => $objet->version]
                + $this->refDoublon($doublon);
        }

        $objet->update($op['payload']); // le hook updating incrémente version
        $this->synchroniserTags($objet, $op);

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
     * Matérialise un conflit « garder les deux » : crée un doublon de la version
     * locale rejetée, marqué en_conflit, rattaché au même parent que l'officiel
     * (ou à la racine de la maison si le parent a disparu). Cf. §5.1 et §5.3.
     *
     * @param  array<string,mixed>  $op
     * @param  \Illuminate\Database\Eloquent\Model|null  $original  L'objet officiel (présent en update-vs-update).
     */
    private function materialiserConflit(array $op, bool $originalDisparu, $original = null)
    {
        $modele = $this->modele($op['entite']);
        $champs = $this->champs($op['payload']);

        // Nom suffixé pour distinguer le doublon dans l'arbre.
        $champs['name'] = $this->suffixerConflit($champs['name'] ?? ($original->name ?? 'Sans nom'));

        // Marquage de conflit + identité neuve (version repart à 1).
        $champs['uuid']       = (string) Str::uuid();
        $champs['version']    = 1;
        $champs['en_conflit'] = true;
        $champs['conflit_de'] = $op['uuid'];

        if ($op['entite'] === 'item') {
            $champs = $this->resoudreParent($champs, $original, $originalDisparu);
            // Le doublon doit posséder sa propre image (suppression indépendante).
            if (! empty($champs['image_filename'])) {
                $champs['image_filename'] = (new ImageService())->dupliquer($champs['image_filename']);
            }
        }

        $doublon = $modele::create($champs);
        $this->synchroniserTags($doublon, $op);

        return $doublon;
    }

    /**
     * Détermine house_id/parent_id du doublon d'item.
     * - update-vs-update : on suit l'officiel (même emplacement).
     * - update-vs-delete : le parent a disparu → racine de la maison (parent_id null).
     *
     * @param  array<string,mixed>  $champs
     * @return array<string,mixed>
     */
    private function resoudreParent(array $champs, $original, bool $originalDisparu): array
    {
        if (! $originalDisparu && $original) {
            $champs['house_id']  = $original->house_id;
            $champs['parent_id'] = $original->parent_id;

            return $champs;
        }

        // L'original a disparu : on s'appuie sur le payload local, mais on
        // retombe à la racine si le parent référencé n'existe plus.
        $parentId = $champs['parent_id'] ?? null;
        if ($parentId !== null && ! Item::whereKey($parentId)->exists()) {
            $champs['parent_id'] = null;
        }

        return $champs;
    }

    /** Suffixe « (conflit — auteur) » ou « (conflit) » si l'auteur est inconnu. */
    private function suffixerConflit(string $nom): string
    {
        $marque = $this->auteur ? "(conflit — {$this->auteur})" : '(conflit)';

        return trim($nom) . ' ' . $marque;
    }

    /**
     * Synchronise les tags d'un item depuis le payload (clé `tags` = liste de
     * noms). No-op pour les houses ou si la clé est absente. firstOrCreate du
     * vocabulaire, puis sync (remplace l'ensemble — cohérent granularité objet).
     *
     * @param  array<string,mixed>  $op
     */
    private function synchroniserTags($objet, array $op): void
    {
        if ($op['entite'] !== 'item' || ! array_key_exists('tags', $op['payload'])) {
            return;
        }

        $ids = [];
        foreach ((array) $op['payload']['tags'] as $nom) {
            $nom = strtolower(trim((string) $nom));
            if ($nom === '') {
                continue;
            }
            $ids[] = Tag::firstOrCreate(['name' => $nom])->id;
        }

        $objet->tags()->sync($ids);
    }

    /**
     * Filtre le payload pour ne garder que des colonnes assignables (retire `tags`,
     * traité à part). Évite un MassAssignmentException / colonne inexistante.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function champs(array $payload): array
    {
        unset($payload['tags']);

        return $payload;
    }

    /**
     * Référence du doublon de conflit à renvoyer au client (id/uuid pour le pull).
     *
     * @return array<string,mixed>
     */
    private function refDoublon($doublon): array
    {
        return ['doublon' => ['id' => $doublon->id, 'uuid' => $doublon->uuid]];
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
