<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Illuminate\Database\Eloquent\Model;

/**
 * Membership of a user in a society, including the invitation handshake.
 */
class SocietyUser extends Model
{
    use AsPivot;

    protected $table = 'society_user';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'invited';
    }
}
