<?php

namespace App\Models;

use Database\Factories\AppMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['app_conversation_id', 'role', 'content', 'citations'])]
class AppMessage extends Model
{
    /** @use HasFactory<AppMessageFactory> */
    use HasFactory;

    /**
     * Bump the parent conversation's `updated_at` so the newest chat sorts first.
     *
     * @var list<string>
     */
    protected $touches = ['conversation'];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AppConversation::class, 'app_conversation_id');
    }
}
