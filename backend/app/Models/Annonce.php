<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Annonce extends Model
{
    protected $table = 'annonces';

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function offresRepas(): HasMany
    {
        return $this->hasMany(OffreRepas::class, 'annonce_id')
            ->where('est_active', true)
            ->orderBy('debut_retrait')
            ->orderBy('id');
    }

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
            'prix' => 'decimal:2',
            'debut_retrait' => 'immutable_datetime',
            'fin_retrait' => 'immutable_datetime',
        ];
    }
}
