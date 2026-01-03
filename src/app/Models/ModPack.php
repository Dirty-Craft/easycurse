<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModPack extends Model
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
        'share_token',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mod_packs';

    /**
     * Get the user that owns the mod pack.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mod pack items for the mod pack.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ModPackItem::class)->orderBy('sort_order');
    }

    /**
     * Generate a unique share token for this mod pack.
     */
    public function generateShareToken(): string
    {
        $token = bin2hex(random_bytes(32)); // 64 character hex string

        // Ensure uniqueness (very unlikely collision, but just in case)
        while (self::where('share_token', $token)->exists()) {
            $token = bin2hex(random_bytes(32));
        }

        $this->update(['share_token' => $token]);

        return $token;
    }

    /**
     * Regenerate the share token (invalidates previous link).
     */
    public function regenerateShareToken(): string
    {
        return $this->generateShareToken();
    }

    /**
     * Get the share URL for this mod pack.
     */
    public function getShareUrl(): ?string
    {
        if (! $this->share_token) {
            return null;
        }

        return url("/shared/{$this->share_token}");
    }
}
