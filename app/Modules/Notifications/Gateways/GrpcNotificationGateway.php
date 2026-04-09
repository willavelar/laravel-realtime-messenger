<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\gRPC\Generated\EmailRequest;
use App\Modules\Notifications\gRPC\Generated\NotificationServiceClient;
use App\Modules\Notifications\gRPC\Generated\PushRequest;
use Illuminate\Support\Facades\Log;

class GrpcNotificationGateway implements NotificationGatewayInterface
{
    private ?NotificationServiceClient $client = null;

    private function getClient(): NotificationServiceClient
    {
        if ($this->client === null) {
            $host = config('grpc.notification_service.host');
            $port = config('grpc.notification_service.port');

            $this->client = new NotificationServiceClient(
                "{$host}:{$port}",
                ['credentials' => \Grpc\ChannelCredentials::createInsecure()],
            );
        }

        return $this->client;
    }

    public function sendPush(array $deviceTokens, string $title, string $body, array $data = []): bool
    {
        $request = new PushRequest();
        $request->setDeviceTokens($deviceTokens);
        $request->setTitle($title);
        $request->setBody($body);
        $request->setData($data);

        $timeout = config('grpc.notification_service.timeout') * 1000;

        [$response, $status] = $this->getClient()->SendPush($request, [], ['timeout' => $timeout])->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC SendPush failed', ['code' => $status->code, 'details' => $status->details]);
            return false;
        }

        return $response->getSuccess();
    }

    public function sendEmail(string $to, string $subject, string $template, array $variables = []): bool
    {
        $request = new EmailRequest();
        $request->setTo($to);
        $request->setSubject($subject);
        $request->setTemplate($template);
        $request->setVariables($variables);

        $timeout = config('grpc.notification_service.timeout') * 1000;

        [$response, $status] = $this->getClient()->SendEmail($request, [], ['timeout' => $timeout])->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC SendEmail failed', ['code' => $status->code, 'details' => $status->details]);
            return false;
        }

        return $response->getSuccess();
    }
}
