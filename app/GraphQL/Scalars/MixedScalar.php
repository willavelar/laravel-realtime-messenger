<?php declare(strict_types=1);

namespace App\GraphQL\Scalars;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * Accepts any JSON-compatible value: object, array, string, number, boolean, null.
 */
class MixedScalar extends ScalarType
{
    public string $name = 'Mixed';

    public ?string $description = 'Accepts any JSON-compatible value: object, array, string, number, boolean, or null.';

    /** @param mixed $value */
    public function serialize($value): mixed
    {
        return $value;
    }

    /** @param mixed $value */
    public function parseValue($value): mixed
    {
        return $value;
    }

    /**
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  array<string, mixed>|null  $variables
     */
    public function parseLiteral($valueNode, ?array $variables = null): mixed
    {
        if ($valueNode instanceof NullValueNode) {
            return null;
        }

        if ($valueNode instanceof BooleanValueNode) {
            return $valueNode->value;
        }

        if ($valueNode instanceof IntValueNode) {
            return (int) $valueNode->value;
        }

        if ($valueNode instanceof FloatValueNode) {
            return (float) $valueNode->value;
        }

        if ($valueNode instanceof StringValueNode) {
            return $valueNode->value;
        }

        if ($valueNode instanceof ListValueNode) {
            return array_map(
                fn ($item) => $this->parseLiteral($item, $variables),
                iterator_to_array($valueNode->values),
            );
        }

        if ($valueNode instanceof ObjectValueNode) {
            $obj = [];
            foreach ($valueNode->fields as $field) {
                $obj[$field->name->value] = $this->parseLiteral($field->value, $variables);
            }
            return $obj;
        }

        return null;
    }
}
