<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use PHPUnit\Framework\TestCase;

class ConstructorExecutionTransformerTest extends TestCase
{
    protected static ConstructorExecutionTransformer $transformer;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        self::$transformer = new ConstructorExecutionTransformer();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listOfExpressions')]
    public function testCanTransformNewExpressions($source, $expected): void
    {
        $stream   = fopen('php://input', 'r');
        $metadata = new StreamMetaData($stream, "<?php $source; ?>");

        self::$transformer->transform($metadata);
        $output = "<?php $expected; ?>";
        $this->assertEquals($output, $metadata->source);
        fclose($stream);
    }

    public static function listOfExpressions(): array
    {
        return [
            [
                '$a = new stdClass',
                '$a = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}'
            ],
            [
                '$b = new stdClass()',
                '$b = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}()'
            ],
            [
                '$stdClass = "stdClass"; $c = new $stdClass',
                '$stdClass = "stdClass"; $c = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{$stdClass}'
            ],
            [
                '$stdClass = "stdClass"; $d = new $stdClass()',
                '$stdClass = "stdClass"; $d = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{$stdClass}()'
            ],
            [
                '$e = new \Exception',
                '$e = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{\Exception::class}'
            ],
            [
                '$f = new \Exception("Test")',
                '$f = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{\Exception::class}("Test")'
            ],
            [
                '$g = new self',
                '$g = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{self::class}',
            ],
            [
                '$h = new static()',
                '$h = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::class}()'
            ],
            [
                '$j = new self::$stdClass()',
                '$j = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{self::$stdClass}()'
            ],
            [
                '$k = new static::$exception["Exception"]("Test")',
                '$k = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::$exception["Exception"]}("Test")'
            ],
            [
                '$l = new self::$object[0]->name("Test Message")',
                '$l = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{self::$object[0]->name}("Test Message")'
            ],
            [
                '$m = new static::$object[0]->name',
                '$m = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::$object[0]->name}'
            ],
            [
                '$n = new stdClass(new static::$object[0]->name)',
                '$n = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}(\Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::$object[0]->name})'
            ],
            // PHP 8.1 new in initializers (issue #603): `new` inside constant-expression
            // contexts must stay untouched — the rewrite is not a valid constant expression.
            'parameter default value' => [
                'function a($helper = new stdClass("x")) { return $helper; }',
                'function a($helper = new stdClass("x")) { return $helper; }'
            ],
            'static variable initializer' => [
                'function b() { static $memo = new \ArrayObject(); return $memo; }',
                'function b() { static $memo = new \ArrayObject(); return $memo; }'
            ],
            'global constant initializer' => [
                'const GLOBAL_SERVICE = new stdClass',
                'const GLOBAL_SERVICE = new stdClass'
            ],
            'attribute argument' => [
                '#[SomeAttr(new stdClass)] function c() {}',
                '#[SomeAttr(new stdClass)] function c() {}'
            ],
            'parameter default kept while body is still rewritten' => [
                'function d($helper = new stdClass) { return new stdClass; }',
                'function d($helper = new stdClass) { return \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}; }'
            ],
            'static variable kept while body is still rewritten' => [
                'function e() { static $memo = new stdClass; $memo->x = new stdClass(); return $memo; }',
                'function e() { static $memo = new stdClass; $memo->x = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}(); return $memo; }'
            ],
            'nested new inside parameter default' => [
                'function f($helper = new stdClass(new stdClass())) {}',
                'function f($helper = new stdClass(new stdClass())) {}'
            ],
            'promoted property hook body is still rewritten' => [
                'class G { public function __construct(public stdClass $h = new stdClass { get { return new stdClass; } }) {} }',
                'class G { public function __construct(public stdClass $h = new stdClass { get { return \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}; } }) {} }'
            ],
        ];
    }
}
