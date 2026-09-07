<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'owner_id', 'document_limit'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_limit' => 'integer',
            'status' => OrganizationStatus::class,
        ];
    }

    /**
     * Whether this organization is disabled — its users are locked out of the
     * system. `status` is deliberately not mass-assignable; transitions go
     * through the admin status endpoint.
     */
    public function isDisabled(): bool
    {
        return $this->status?->isDisabled() ?? false;
    }

    /**
     * The document ceiling that applies to this organization: its own
     * `document_limit` when set, otherwise the platform default. Null means
     * unlimited.
     */
    public function effectiveDocumentLimit(): ?int
    {
        $default = config('raas.organizations.default_document_limit');

        return $this->document_limit ?? ($default === null ? null : (int) $default);
    }

    /**
     * A URL-safe slug derived from the name, with a random suffix to guarantee
     * uniqueness.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
