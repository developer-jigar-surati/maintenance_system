<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A record of one message actually leaving the system.
 *
 * Kept so a committee can answer "was I ever told?" with a row rather than an
 * opinion, and so a reminder rule that runs twice in a day does not send the
 * same nudge twice: the dedupe key is unique per society.
 */
class MessageDispatch extends Model
{
    use BelongsToSociety, HasFactory;

    protected $fillable = [
        'society_id', 'template_key', 'channel', 'user_id', 'recipient',
        'subject', 'body', 'related_type', 'related_id', 'dedupe_key',
        'status', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
