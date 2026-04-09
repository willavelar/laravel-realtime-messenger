<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;

class PresenceService
{
    public function markOnline(User $user): User
    {
        $user->update(['is_online' => true, 'last_seen_at' => now()]);
        return $user->fresh();
    }

    public function markOffline(User $user): User
    {
        $user->update(['is_online' => false, 'last_seen_at' => now()]);
        return $user->fresh();
    }
}
