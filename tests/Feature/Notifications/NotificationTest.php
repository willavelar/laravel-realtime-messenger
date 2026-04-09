<?php

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();
        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'message',
            'data' => ['message' => 'Hello'],
        ]);

        $response = $this->actingAs($user)->postGraphQL([
            'query' => '{ notifications { id type data } }',
        ]);

        $this->assertCount(1, $response->json('data.notifications'));
    }

    public function test_user_can_get_unread_count(): void
    {
        $user = User::factory()->create();
        AppNotification::create(['user_id' => $user->id, 'type' => 'message', 'data' => []]);
        AppNotification::create(['user_id' => $user->id, 'type' => 'system', 'data' => [], 'read_at' => now()]);

        $response = $this->actingAs($user)->postGraphQL([
            'query' => '{ unreadNotificationsCount }',
        ]);

        $this->assertEquals(1, $response->json('data.unreadNotificationsCount'));
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'message',
            'data' => [],
        ]);

        $response = $this->actingAs($user)->postGraphQL([
            'query' => 'mutation($id: ID!) { markNotificationAsRead(id: $id) { id read_at } }',
            'variables' => ['id' => $notification->id],
        ]);

        $this->assertNotNull($response->json('data.markNotificationAsRead.read_at'));
    }
}
