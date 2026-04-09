<?php

namespace App\Modules\Auth\GraphQL\Mutations;

use App\Modules\Auth\Services\AuthService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class LogoutMutation
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $this->authService->logout($context->user());
        return true;
    }
}
