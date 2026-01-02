<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModPackItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mod_pack_id',
        'mod_name',
        'mod_version',
        'sort_order',
        'curseforge_mod_id',
        'curseforge_file_id',
        'curseforge_slug',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mod_pack_items';

    /**
     * Get the mod pack that owns the item.
     */
    public function modPack(): BelongsTo
    {
        return $this->belongsTo(ModPack::class);
    }
}
