<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Database\Factories\AppPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A project published to an organization's employees: a public slug, an access
 * code, and the copy shown on the employee app's landing + chat.
 */
#[Fillable(['welcome_message', 'suggested_questions', 'daily_query_limit', 'is_active'])]
class AppPublication extends Model
{
    /** @use HasFactory<AppPublicationFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'suggested_questions' => 'array',
            'daily_query_limit' => 'integer',
        ];
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'app';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::withoutGlobalScopes()->where('slug', $slug)->exists());

        return $slug;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AppConversation::class);
    }
}
