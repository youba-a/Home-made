<?php

namespace App\Http\Resources;

use App\Models\Annonce;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Annonce
 */
class AnnonceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offres = $this->relationLoaded('offresRepas') ? $this->offresRepas : collect();
        $lieu = $this->type === 'OBJET'
            ? ($this->relationLoaded('lieuRetrait') ? $this->lieuRetrait : null)
            : $offres->first()?->lieuRetrait;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'titre' => $this->titre,
            'description' => $this->description,
            'prix' => $this->prix,
            'image_url' => asset($this->image),
            'categorie' => $this->categorie,
            'etat_objet' => $this->etat_objet,
            'zone' => $lieu?->zone?->nom,
            'commune' => $lieu?->commune?->nom,
            'disponible' => $this->type === 'OBJET'
                ? $this->etat_objet === 'DISPONIBLE' && $this->debut_retrait?->isFuture() === true
                : $offres->contains(fn ($offre) => $offre->portions_restantes > 0 && $offre->limite_achat->isFuture()),
            'retrait' => $this->type === 'OBJET' && $lieu ? [
                'lieu' => new LieuRetraitResource($lieu),
                'debut_retrait' => $this->debut_retrait?->toISOString(),
                'fin_retrait' => $this->fin_retrait?->toISOString(),
            ] : null,
            'vendeur' => $this->whenLoaded('vendeur', fn () => [
                'id' => $this->vendeur->id,
                'nom' => $this->vendeur->name,
            ]),
            'offres_repas' => OffreRepasResource::collection($offres),
        ];
    }
}
