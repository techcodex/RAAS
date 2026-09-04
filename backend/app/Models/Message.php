<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'role', 'content', 'citations', 'usage'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'usage' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
