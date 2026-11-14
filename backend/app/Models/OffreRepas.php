<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffreRepas extends Model
{
    protected $table = 'offres_repas';

    public function lieuRetrait(): BelongsTo
    {
        return $this->belongsTo(LieuRetrait::class, 'lieu_retrait_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portions_restantes' => 'integer',
            'est_active' => 'boolean',
            'limite_achat' => 'immutable_datetime',
            'debut_retrait' => 'immutable_datetime',
            'fin_retrait' => 'immutable_datetime',
        ];
    }
}
