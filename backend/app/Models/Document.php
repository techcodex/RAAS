<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Support\BelongsToOrganization;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'project_id',
    'uploaded_by_user_id',
    'original_filename',
    'disk',
    'path',
    'mime_type',
    'size_bytes',
    'status',
    'error_message',
    'chunking_strategy',
    'chunking_config',
    'chunk_count',
    'processed_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size_bytes' => 'integer',
            'chunk_count' => 'integer',
            'chunking_config' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * Mark the document as failed with a short reason. Truncated to fit the column.
     */
    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => DocumentStatus::Failed,
            'error_message' => mb_substr($reason, 0, 1000),
        ]);
    }

    /**
     * Delete the underlying stored file. Called from the model's deleting event.
     */
    public function deleteStoredFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    protected static function booted(): void
    {
        static::deleting(function (Document $document) {
            $document->deleteStoredFile();
        });
    }
}
