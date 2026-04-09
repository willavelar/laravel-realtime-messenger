<?php

namespace App\Modules\Auth\GraphQL\Mutations;

use App\Modules\Auth\Services\AuthService;

class RegisterMutation
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(mixed $root, array $args): array
    {
        return $this->authService->register(
            name: $args['name'],
            email: $args['email'],
            password: $args['password'],
        );
    }
}
