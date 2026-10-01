<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeRead extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class, 'notice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
