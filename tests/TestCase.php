<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;

abstract class TestCase extends BaseTestCase
{
    use MakesGraphQLRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(
            \App\Modules\Notifications\Contracts\NotificationGatewayInterface::class,
            \App\Modules\Notifications\Gateways\FakeNotificationGateway::class,
        );
    }
}

