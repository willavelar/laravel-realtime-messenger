<?php

namespace App\Modules\Notifications\gRPC\Generated;

class NotificationServiceClient
{
    public function __construct(string $hostname, array $opts = [])
    {
        // Stub — real implementation extends \Grpc\BaseStub in Docker
    }

    public function SendPush(PushRequest $argument, array $metadata = [], array $options = []): object
    {
        return $this->_simpleRequest(
            '/notifications.NotificationService/SendPush',
            $argument,
            [PushResponse::class, 'decode'],
            $metadata,
            $options,
        );
    }

    public function SendEmail(EmailRequest $argument, array $metadata = [], array $options = []): object
    {
        return $this->_simpleRequest(
            '/notifications.NotificationService/SendEmail',
            $argument,
            [EmailResponse::class, 'decode'],
            $metadata,
            $options,
        );
    }

    protected function _simpleRequest(string $method, object $argument, array $deserialize, array $metadata = [], array $options = []): object
    {
        // Stub — real implementation provided by grpc extension via BaseStub
        throw new \RuntimeException('NotificationServiceClient is a local stub. Run protoc inside Docker to regenerate real client files.');
    }
}
