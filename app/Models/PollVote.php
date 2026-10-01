<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollVote extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'rating' => 'integer',
            'voted_at' => 'datetime',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
