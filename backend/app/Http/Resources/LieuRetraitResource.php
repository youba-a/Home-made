<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LieuRetraitResource extends JsonResource
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
            'type' => $this->type,
            'zone' => ['id' => $this->zone->id, 'nom' => $this->zone->nom],
            'commune' => [
                'id' => $this->commune->id,
                'nom' => $this->commune->nom,
                'code_postal' => $this->commune->code_postal,
            ],
        ];
    }
}
