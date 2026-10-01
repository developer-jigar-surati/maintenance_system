<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingAgendaItem extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(MeetingResolution::class, 'meeting_agenda_item_id');
    }
}
