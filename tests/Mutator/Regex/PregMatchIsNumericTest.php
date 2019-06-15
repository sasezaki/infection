<?php
declare(strict_types=1);

namespace Infection\Tests\Mutator\Regex;


use Infection\Tests\Mutator\AbstractMutatorTestCase;

/**
 * @internal
 */
final class PregMatchIsNumericTest extends AbstractMutatorTestCase
{
    /**
     * @dataProvider providesMutatorCases
     */
    public function test_mutator(string $input, string $output = null): void
    {
        $this->doTest($input, $output);
    }

    public function providesMutatorCases(): \Generator
    {
        yield 'It mutates ' => [
            <<<'PHP'
<?php

preg_match('/\A[0-9]+\z/', '-1.23');
PHP
            ,
            <<<'PHP'
<?php

is_numeric('-1.23');
PHP
        ];
    }
}