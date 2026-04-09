<?php

namespace App\Modules\Notifications\Contracts;

interface NotificationGatewayInterface
{
    /**
     * @param string[] $deviceTokens
     * @param array<string, string> $data
     */
    public function sendPush(array $deviceTokens, string $title, string $body, array $data = []): bool;

    /**
     * @param array<string, string> $variables
     */
    public function sendEmail(string $to, string $subject, string $template, array $variables = []): bool;
}
