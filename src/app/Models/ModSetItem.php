<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModSetItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mod_set_id',
        'mod_name',
        'mod_version',
        'sort_order',
    ];

    /**
     * Get the mod set that owns the item.
     */
    public function modSet(): BelongsTo
    {
        return $this->belongsTo(ModSet::class);
    }
}
