<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LieuRetrait extends Model
{
    protected $table = 'lieux_retrait';

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
