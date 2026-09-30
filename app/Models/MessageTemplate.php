<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use App\Support\MessageCatalogue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A society's own wording for one of the messages the system sends.
 *
 * Absence is meaningful: with no row, the packaged default in the catalogue is
 * used. That keeps a society that has never opened the editor communicating
 * correctly, and makes "reset to the default wording" a delete.
 */
class MessageTemplate extends Model
{
    use BelongsToSociety, HasFactory;

    protected $fillable = [
        'society_id', 'key', 'channel', 'name', 'subject', 'body', 'is_active', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return array<string, string> */
    public function placeholders(): array
    {
        return MessageCatalogue::placeholders($this->key);
    }

    public function scopeForKey($query, string $key, string $channel = 'email')
    {
        return $query->where('key', $key)->where('channel', $channel);
    }
}
