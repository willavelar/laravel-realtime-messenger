<?php

namespace App\Modules\Auth\GraphQL\Mutations;

use App\Modules\Auth\Services\AuthService;

class LoginMutation
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(mixed $root, array $args): array
    {
        return $this->authService->login(
            email: $args['email'],
            password: $args['password'],
        );
    }
}
