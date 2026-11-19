<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Zone::query()->orderBy('nom')->get(['id', 'nom'])]);
    }

    public function communes(): JsonResponse
    {
        return response()->json(['data' => Commune::query()
            ->orderBy('nom')->orderBy('code_postal')->orderBy('id')
            ->get(['id', 'nom', 'code_postal'])]);
    }
}
