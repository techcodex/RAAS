<?php

namespace App\Models;

use Database\Factories\AppUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * An employee an org owner has granted access to a published app. A separate
 * identity from `User` — an AppUser token cannot reach the `/api/v1/*` routes.
 * The owner sets name/email/access_code and can deactivate the employee.
 */
#[Fillable(['app_publication_id', 'name', 'email', 'access_code', 'is_active'])]
class AppUser extends Authenticatable
{
    /** @use HasFactory<AppUserFactory> */
    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'access_code' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(AppPublication::class, 'app_publication_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AppConversation::class);
    }
}
