<?php

namespace App\Http\Resources;

use App\Models\OffreRepas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OffreRepas
 */
class OffreRepasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repas_id' => $this->repas_id,
            'portions_restantes' => $this->portions_restantes,
            'limite_achat' => $this->limite_achat->toISOString(),
            'debut_retrait' => $this->debut_retrait->toISOString(),
            'fin_retrait' => $this->fin_retrait->toISOString(),
            'disponible' => $this->est_active && $this->portions_restantes > 0 && $this->limite_achat->isFuture(),
            'lieu_retrait' => new LieuRetraitResource($this->lieuRetrait),
        ];
    }
}
