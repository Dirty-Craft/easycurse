<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModPackRun extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mod_pack_id',
        'is_completed',
        'output',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mod_pack_runs';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }

    /**
     * Get the mod pack that owns the run.
     */
    public function modPack(): BelongsTo
    {
        return $this->belongsTo(ModPack::class);
    }
}
