<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Instrument\Transformer\Stubs\ConstructedStub;
use Go\Instrument\Transformer\Stubs\InitializationAwareStub;
use LogicException;
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
    public function testCanTransformNewExpressions(string $source, string $expected): void
    {
        $stream   = fopen('php://input', 'r');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, "<?php $source; ?>");

        self::$transformer->transform($metadata);
        $output = "<?php $expected; ?>";
        $this->assertEquals($output, $metadata->source);
        fclose($stream);
    }

    /**
     * Sources that contain a rewritable `new` expression are reported as transformed, while
     * sources without any (or with `new` only in constant-expression contexts) abstain.
     */
    public function testTransformReportsTransformedOnlyForRewrittenExpressions(): void
    {
        $this->assertSame(
            TransformerResultEnum::RESULT_TRANSFORMED,
            self::$transformer->transform($this->createMetadata('<?php $a = new \stdClass();')),
        );
        $this->assertSame(
            TransformerResultEnum::RESULT_ABSTAIN,
            self::$transformer->transform($this->createMetadata('<?php $a = 42;')),
        );
        // `new` in a constant-expression context is skipped, so nothing is left to rewrite
        $this->assertSame(
            TransformerResultEnum::RESULT_ABSTAIN,
            self::$transformer->transform($this->createMetadata('<?php const SERVICE = new \stdClass;')),
        );
    }

    /**
     * The transformer is used as a singleton by the code it injects into the sources.
     */
    public function testGetInstanceAlwaysReturnsTheSameInstance(): void
    {
        $instance = ConstructorExecutionTransformer::getInstance();

        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertInstanceOf(ConstructorExecutionTransformer::class, $instance);
        $this->assertSame($instance, ConstructorExecutionTransformer::getInstance());
    }

    /**
     * `new Foo` without parentheses is rewritten to a property read on the singleton, which is
     * served by __get() and must construct the class without any constructor arguments.
     */
    public function testMagicPropertyAccessCreatesInstanceWithoutArguments(): void
    {
        $transformer = ConstructorExecutionTransformer::getInstance();

        // @phpstan-ignore property.notFound (exercises the __get() magic method directly)
        $instance = $transformer->{ConstructedStub::class};

        $this->assertInstanceOf(ConstructedStub::class, $instance);
        $this->assertSame('default', $instance->name);
        $this->assertSame(0, $instance->size);
    }

    /**
     * `new Foo($a, $b)` is rewritten to a method call on the singleton, so __call() has to pass
     * the constructor arguments through. A leading backslash in the class name is tolerated.
     */
    public function testMagicMethodCallCreatesInstanceWithArguments(): void
    {
        $transformer = ConstructorExecutionTransformer::getInstance();

        // @phpstan-ignore method.notFound (exercises the __call() magic method directly)
        $instance = $transformer->{'\\' . ConstructedStub::class}('custom', 5);

        $this->assertInstanceOf(ConstructedStub::class, $instance);
        $this->assertSame('custom', $instance->name);
        $this->assertSame(5, $instance->size);
    }

    /**
     * Classes woven with an initialization advice expose __aop__initialization(); instantiation
     * has to be delegated to it instead of going through a ReflectionConstructorInvocation.
     */
    public function testInitializationAwareClassIsCreatedThroughItsEntryPoint(): void
    {
        $transformer = ConstructorExecutionTransformer::getInstance();

        // @phpstan-ignore method.notFound (exercises the __call() magic method directly)
        $instance = $transformer->{InitializationAwareStub::class}('first', 2);

        $this->assertInstanceOf(InitializationAwareStub::class, $instance);
        $this->assertSame(['first', 2], $instance->receivedArguments);

        // The same class must keep using the entry point on repeated (cached) lookups
        // @phpstan-ignore property.notFound (exercises the __get() magic method directly)
        $second = $transformer->{InitializationAwareStub::class};
        $this->assertInstanceOf(InitializationAwareStub::class, $second);
        $this->assertSame([], $second->receivedArguments);
    }

    /**
     * A `new` on an unknown class name cannot be dispatched: the cached invocation stays null
     * and the transformer reports the failure instead of emitting a confusing TypeError.
     */
    public function testUnknownClassNameThrowsLogicException(): void
    {
        $transformer = ConstructorExecutionTransformer::getInstance();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot instantiate non-existent class: Go\Instrument\Transformer\Stubs\MissingStub');

        // @phpstan-ignore property.notFound, expr.resultUnused (exercises the __get() magic method directly)
        $transformer->{'Go\Instrument\Transformer\Stubs\MissingStub'};
    }

    private function createMetadata(string $source): StreamMetaData
    {
        $stream = fopen('php://input', 'r');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

    /**
     * @return array<array{string, string}>
     */
    public static function listOfExpressions(): array
    {
        return [
            [
                '$a = new stdClass',
                '$a = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}',
            ],
            [
                '$b = new stdClass()',
                '$b = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}()',
            ],
            [
                '$stdClass = "stdClass"; $c = new $stdClass',
                '$stdClass = "stdClass"; $c = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{$stdClass}',
            ],
            [
                '$stdClass = "stdClass"; $d = new $stdClass()',
                '$stdClass = "stdClass"; $d = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{$stdClass}()',
            ],
            [
                '$e = new \Exception',
                '$e = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{\Exception::class}',
            ],
            [
                '$f = new \Exception("Test")',
                '$f = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{\Exception::class}("Test")',
            ],
            [
                '$g = new self',
                '$g = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{self::class}',
            ],
            [
                '$h = new static()',
                '$h = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::class}()',
            ],
            [
                '$j = new self::$stdClass()',
                '$j = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{self::$stdClass}()',
            ],
            [
                '$k = new static::$exception["Exception"]("Test")',
                '$k = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::$exception["Exception"]}("Test")',
            ],
            [
                '$l = new self::$object[0]->name("Test Message")',
                '$l = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{self::$object[0]->name}("Test Message")',
            ],
            [
                '$m = new static::$object[0]->name',
                '$m = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::$object[0]->name}',
            ],
            [
                '$n = new stdClass(new static::$object[0]->name)',
                '$n = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}(\Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{static::$object[0]->name})',
            ],
            // PHP 8.1 new in initializers (issue #603): `new` inside constant-expression
            // contexts must stay untouched — the rewrite is not a valid constant expression.
            'parameter default value' => [
                'function a($helper = new stdClass("x")) { return $helper; }',
                'function a($helper = new stdClass("x")) { return $helper; }',
            ],
            'static variable initializer' => [
                'function b() { static $memo = new \ArrayObject(); return $memo; }',
                'function b() { static $memo = new \ArrayObject(); return $memo; }',
            ],
            'global constant initializer' => [
                'const GLOBAL_SERVICE = new stdClass',
                'const GLOBAL_SERVICE = new stdClass',
            ],
            'attribute argument' => [
                '#[SomeAttr(new stdClass)] function c() {}',
                '#[SomeAttr(new stdClass)] function c() {}',
            ],
            'parameter default kept while body is still rewritten' => [
                'function d($helper = new stdClass) { return new stdClass; }',
                'function d($helper = new stdClass) { return \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}; }',
            ],
            'static variable kept while body is still rewritten' => [
                'function e() { static $memo = new stdClass; $memo->x = new stdClass(); return $memo; }',
                'function e() { static $memo = new stdClass; $memo->x = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}(); return $memo; }',
            ],
            'nested new inside parameter default' => [
                'function f($helper = new stdClass(new stdClass())) {}',
                'function f($helper = new stdClass(new stdClass())) {}',
            ],
            'promoted property hook body is still rewritten' => [
                'class G { public function __construct(public stdClass $h = new stdClass { get { return new stdClass; } }) {} }',
                'class G { public function __construct(public stdClass $h = new stdClass { get { return \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{stdClass::class}; } }) {} }',
            ],
        ];
    }
}
