<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One step of a reminder schedule: how many days before or after the due date
 * a message goes out, and through which channels.
 *
 * Offsets are signed days -- -3 is three days before, 7 is a week after -- so
 * the whole ladder reads as a list of numbers a committee can reason about
 * without knowing what a cron expression is.
 */
class ReminderRule extends Model
{
    use BelongsToSociety, HasFactory;

    protected $fillable = [
        'society_id', 'event', 'label', 'offset_days', 'template_key',
        'channels', 'is_active', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
            'channels' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public const EVENTS = [
        'invoice_due' => 'Unpaid maintenance bill',
        'complaint_breach' => 'Complaint about to breach its SLA',
        'meeting' => 'Upcoming meeting',
        'amenity_booking' => 'Upcoming amenity booking',
        'document_expiry' => 'Document about to expire',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /** Reads the offset back as something a person would say out loud. */
    public function timingLabel(): string
    {
        return match (true) {
            $this->offset_days < 0 => abs($this->offset_days).' '.$this->plural(abs($this->offset_days)).' before',
            $this->offset_days === 0 => 'On the day',
            default => $this->offset_days.' '.$this->plural($this->offset_days).' after',
        };
    }

    private function plural(int $days): string
    {
        return $days === 1 ? 'day' : 'days';
    }
}
