<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingResolution extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'votes_for' => 'decimal:2',
            'votes_against' => 'decimal:2',
            'votes_abstain' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'meeting_agenda_item_id');
    }
}
