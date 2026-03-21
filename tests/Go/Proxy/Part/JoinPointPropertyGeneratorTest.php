<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use PHPUnit\Framework\TestCase;

/**
 * Test case for joinPoint property generator
 */
class JoinPointPropertyGeneratorTest extends TestCase
{
    /**
     * Tests that generator can generate valid joinpoint property
     */
    public function testGenerate(): void
    {
        $generator = new JoinPointPropertyGenerator();
        $this->assertSame('__joinPoints', $generator->getName());
        $propertyCode = $generator->generate();
        $expectedCode = preg_replace(
            '/^\s+|\s+$/m',
            '',
            '/**
             * List of applied advices per class
             *
             * @var array<string, \Go\Aop\Intercept\Joinpoint>
             */
            private static array $__joinPoints = [
            ];'
        );
        $actualCode = preg_replace('/^\s+|\s+$/m', '', $propertyCode);
        $this->assertSame($expectedCode, $actualCode);
    }
}
