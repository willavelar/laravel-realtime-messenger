<?php

return [
    'notification_service' => [
        'host' => env('GRPC_NOTIFICATION_HOST', 'localhost'),
        'port' => env('GRPC_NOTIFICATION_PORT', 50051),
        'timeout' => env('GRPC_NOTIFICATION_TIMEOUT', 5000), // ms
    ],
];
