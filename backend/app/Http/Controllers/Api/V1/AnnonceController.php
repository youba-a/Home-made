<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnonceResource;
use App\Models\Annonce;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnonceController extends Controller
{
    private const RELATIONS = [
        'vendeur:id,name', 'lieuRetrait.zone', 'lieuRetrait.commune',
        'offresRepas.lieuRetrait.zone', 'offresRepas.lieuRetrait.commune',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['sometimes', 'required', 'string', 'in:OBJET,REPAS'],
            'q' => ['nullable', 'string', 'max:100'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $query = Annonce::query()->with(self::RELATIONS);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['q']) && trim($filters['q']) !== '') {
            $text = '%'.trim($filters['q']).'%';
            $query->where(function (Builder $query) use ($text): void {
                $query->where('titre', 'like', $text)
                    ->orWhere('description', 'like', $text)
                    ->orWhere('categorie', 'like', $text);
            });
        }

        if (! empty($filters['zone_id'])) {
            $zoneId = (int) $filters['zone_id'];
            $query->where(function (Builder $query) use ($zoneId): void {
                $query->whereHas('lieuRetrait', fn (Builder $place) => $place->where('zone_id', $zoneId))
                    ->orWhereHas('offresRepas.lieuRetrait', fn (Builder $place) => $place->where('zone_id', $zoneId));
            });
        }

        $annonces = $query
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return AnnonceResource::collection($annonces);
    }

    public function show(Annonce $annonce): AnnonceResource
    {
        $annonce->load(self::RELATIONS);

        return new AnnonceResource($annonce);
    }
}
