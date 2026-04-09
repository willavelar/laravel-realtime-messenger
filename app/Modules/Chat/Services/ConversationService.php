<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Users\Models\User;

class ConversationService
{
    public function createDm(User $userA, User $userB): Conversation
    {
        $existing = Conversation::where('type', 'dm')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userA->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userB->id))
            ->first();

        if ($existing) {
            return $existing->load('participants');
        }

        $conversation = Conversation::create([
            'type' => 'dm',
            'created_by' => $userA->id,
        ]);

        $conversation->participants()->attach([
            $userA->id => ['joined_at' => now()],
            $userB->id => ['joined_at' => now()],
        ]);

        return $conversation->load('participants');
    }

    public function createGroup(User $creator, string $name, array $userIds): Conversation
    {
        $conversation = Conversation::create([
            'type' => 'group',
            'name' => $name,
            'created_by' => $creator->id,
        ]);

        $participants = array_fill_keys(
            array_unique(array_merge([$creator->id], $userIds)),
            ['joined_at' => now()]
        );

        $conversation->participants()->attach($participants);

        return $conversation->load('participants');
    }

    public function isParticipant(Conversation $conversation, User $user): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->exists();
    }
}
