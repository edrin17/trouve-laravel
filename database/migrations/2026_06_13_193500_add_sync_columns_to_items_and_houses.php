<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Colonnes de synchronisation hors-ligne (cf. docs/conception_mode_hors_connexion.md).
 *
 * Sur items et houses :
 *  - uuid        : identité stable générée côté client (échange offline, lien parent/enfant)
 *  - version     : verrouillage optimiste (incrémenté à chaque update serveur)
 *  - en_conflit  : objet issu d'une résolution « garder les deux »
 *  - conflit_de  : uuid de l'objet original dont c'est la copie en conflit
 *
 * Idempotente (hasColumn) pour les bases déjà migrées. Le uuid est ajouté
 * nullable puis backfillé puis indexé unique — SQLite ne permet pas d'ajouter
 * une colonne UNIQUE avec valeur par défaut dynamique en une seule passe.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['items', 'houses'];

    public function up(): void
    {
        foreach ($this->tables as $nomTable) {
            // 1. uuid nullable (sans contrainte unique inline)
            if (! Schema::hasColumn($nomTable, 'uuid')) {
                Schema::table($nomTable, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->after('id');
                });
            }

            // 2. backfill des lignes existantes dépourvues d'uuid
            DB::table($nomTable)->whereNull('uuid')->orderBy('id')->each(function ($ligne) use ($nomTable) {
                DB::table($nomTable)->where('id', $ligne->id)->update(['uuid' => (string) Str::uuid()]);
            });

            // 3. index unique sur uuid (après backfill, sinon collision de NULL/doublons)
            if (! $this->indexExiste($nomTable, "{$nomTable}_uuid_unique")) {
                Schema::table($nomTable, function (Blueprint $table) {
                    $table->unique('uuid');
                });
            }

            // 4. colonnes restantes avec défauts constants (compatibles ALTER SQLite)
            Schema::table($nomTable, function (Blueprint $table) use ($nomTable) {
                if (! Schema::hasColumn($nomTable, 'version')) {
                    $table->unsignedInteger('version')->default(1)->after('uuid');
                }
                if (! Schema::hasColumn($nomTable, 'en_conflit')) {
                    $table->boolean('en_conflit')->default(false);
                }
                if (! Schema::hasColumn($nomTable, 'conflit_de')) {
                    $table->uuid('conflit_de')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) use ($nomTable) {
                if ($this->indexExiste($nomTable, "{$nomTable}_uuid_unique")) {
                    $table->dropUnique("{$nomTable}_uuid_unique");
                }
                foreach (['uuid', 'version', 'en_conflit', 'conflit_de'] as $colonne) {
                    if (Schema::hasColumn($nomTable, $colonne)) {
                        $table->dropColumn($colonne);
                    }
                }
            });
        }
    }

    /** Vrai si un index nommé existe sur la table (portable SQLite/MySQL via Doctrine). */
    private function indexExiste(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $i) => $i['name'] === $index);
    }
};
