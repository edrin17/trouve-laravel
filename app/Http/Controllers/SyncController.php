<?php

namespace App\Http\Controllers;

use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de synchronisation hors-ligne.
 * Cf. docs/conception_mode_hors_connexion.md §4 et §7.
 */
class SyncController extends Controller
{
    public function __construct(private readonly SyncService $sync)
    {
    }

    /** Reçoit le journal d'opérations du client et renvoie un résultat par op. */
    public function push(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'operations'                 => ['present', 'array'],
            'operations.*.op_id'         => ['required', 'uuid'],
            'operations.*.type'          => ['required', 'in:create,update,delete,move,tag,image'],
            'operations.*.entite'        => ['required', 'in:item,house'],
            'operations.*.uuid'          => ['required', 'uuid'],
            'operations.*.base_version'  => ['nullable', 'integer'],
            'operations.*.payload'       => ['nullable', 'array'],
        ]);

        $resultats = $this->sync
            ->pour($request->user()?->name)
            ->push($valide['operations']);

        return response()->json(['resultats' => $resultats]);
    }

    /** Renvoie l'état partagé (full pull en v1) et un curseur serveur. */
    public function pull(Request $request): JsonResponse
    {
        return response()->json(
            $this->sync->pull($request->query('depuis'))
        );
    }
}
