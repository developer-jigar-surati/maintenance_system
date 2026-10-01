<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItem extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
