<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Database\Factories\ProjectCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'provider', 'api_key', 'model'])]
#[Hidden(['api_key'])]
class ProjectCredential extends Model
{
    /** @use HasFactory<ProjectCredentialFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
