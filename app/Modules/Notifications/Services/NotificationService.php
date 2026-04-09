<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function createForUser(User $user, string $type, array $data): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function getForUser(User $user): Collection
    {
        return AppNotification::where('user_id', $user->id)
            ->latest()
            ->get();
    }

    public function countUnread(User $user): int
    {
        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(AppNotification $notification, User $user): AppNotification
    {
        if ($notification->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $notification->update(['read_at' => now()]);
        return $notification->fresh();
    }

    public function markAllAsRead(User $user): void
    {
        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
