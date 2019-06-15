<?php

declare(strict_types=1);

namespace Infection\Mutator\Regex;

use Infection\Mutator\Util\Mutator;
use PhpParser\Node;

/**
 * @internal
 */
class PregMatchIsNumeric extends Mutator
{
    /**
     * Replaces "preg_match('/\A[0-9]+\z/', '-0.12');" with "is_numeric('-0.12');"
     */
    public function mutate(Node $node)
    {
        /** @var Node\Expr\FuncCall $node */
        return new Node\Expr\FuncCall(
            new Node\Name('is_numeric', $node->name->getAttributes()),
            [$node->args[1]],
            $node->getAttributes()
        );
    }

    protected function mutatesNode(Node $node): bool
    {
        if (!$node instanceof Node\Expr\FuncCall) {
            return false;
        }

        if (!$node->name instanceof Node\Name ||
            $node->name->toLowerString() !== 'preg_match') {
            return false;
        }

        if (count($node->args) > 2 ) {
            return false;
        }

        /** @var \PhpParser\Node\Scalar\String_ $pattern */
        $pattern = $node->args[0]->value;
        return '\A[0-9]+\z' === $this->extractRegex($pattern->value);
    }

    private function extractRegex(string $pattern)
    {
        $delimiter       = substr($pattern, 0, 1);
        $delimiterEndPos = strrpos($pattern, $delimiter);
        return substr($pattern, 1, $delimiterEndPos - 1);
    }
}