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
        'modrinth_project_id',
        'modrinth_version_id',
        'modrinth_slug',
        'source',
        'last_update_notified_at',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mod_pack_items';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_update_notified_at' => 'datetime',
        ];
    }

    /**
     * Get the mod pack that owns the item.
     */
    public function modPack(): BelongsTo
    {
        return $this->belongsTo(ModPack::class);
    }
}
