<?php

namespace App\Models;

use Database\Factories\AppConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['app_publication_id', 'app_user_id', 'title'])]
class AppConversation extends Model
{
    /** @use HasFactory<AppConversationFactory> */
    use HasFactory;

    public function publication(): BelongsTo
    {
        return $this->belongsTo(AppPublication::class, 'app_publication_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'app_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AppMessage::class);
    }
}
