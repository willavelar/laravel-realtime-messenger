<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;

class FakeNotificationGateway implements NotificationGatewayInterface
{
    public array $pushedNotifications = [];
    public array $sentEmails = [];

    public function sendPush(array $deviceTokens, string $title, string $body, array $data = []): bool
    {
        $this->pushedNotifications[] = compact('deviceTokens', 'title', 'body', 'data');
        return true;
    }

    public function sendEmail(string $to, string $subject, string $template, array $variables = []): bool
    {
        $this->sentEmails[] = compact('to', 'subject', 'template', 'variables');
        return true;
    }
}
