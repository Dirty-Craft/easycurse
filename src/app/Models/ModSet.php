<?php

namespace App\Models;

use App\Enums\Software;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModSet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'minecraft_version',
        'software',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'software' => Software::class,
        ];
    }

    /**
     * Get the attributes that should be appended to the model's array form.
     *
     * @return array<int, string>
     */
    protected $appends = ['software_label'];

    /**
     * Get the software label.
     */
    public function getSoftwareLabelAttribute(): string
    {
        return $this->software->label();
    }

    /**
     * Get the user that owns the mod set.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mod set items for the mod set.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ModSetItem::class)->orderBy('sort_order');
    }
}
