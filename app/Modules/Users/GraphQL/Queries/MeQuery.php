<?php

namespace App\Modules\Users\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MeQuery
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        return $context->user();
    }
}
