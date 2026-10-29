<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer\Weaving;

use Go\Core\AspectContainer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\ParserReflection\ReflectionFile;
use Go\Proxy\ClassProxyGenerator;
use PHPUnit\Framework\TestCase;

final class TraitConverterTest extends TestCase
{
    public function testClassHeaderBecomesTraitHeader(): void
    {
        $woven = self::convert(
            "<?php\n#[\\Attribute, Foo]\nfinal readonly class Foo extends \\ArrayObject implements\n    \\Countable\n{\n}",
        );

        $this->assertSame("<?php\n#[Foo]\ntrait FooOriginalTrait   \n    \n{\n}", $woven);
    }

    public function testModifierOnOwnLineKeepsLine(): void
    {
        $woven = self::convert("<?php\nabstract\nclass Foo {}");

        $this->assertSame("<?php\n\ntrait FooOriginalTrait {}", $woven);
    }

    public function testEnumLosesBackedTypeInterfacesAndCases(): void
    {
        $woven = self::convert(
            "<?php\nenum Suit: string implements \\JsonSerializable\n{\n    case Hearts = 'H';\n    case Spades = 'S';\n"
            . "    public function jsonSerialize(): mixed { return 1; }\n}",
        );

        $this->assertSame(
            "<?php\ntrait SuitOriginalTrait   \n{\n    \n    \n    public function jsonSerialize(): mixed { return 1; }\n}",
            $woven,
        );
    }

    public function testTraitIsRenamedAndLosesInterceptedProperties(): void
    {
        $woven = self::convert(
            "<?php\ntrait Greeting\n{\n    public string \$name = 'x';\n}",
            [AspectContainer::PROPERTY_PREFIX => ['name' => []]],
        );

        $this->assertSame(
            "<?php\ntrait GreetingOriginalTrait\n{\n    /* Moved by weaving interceptor to the {@see Greeting->name} */\n}",
            $woven,
        );
    }

    public function testOnlyInterceptedItemsOfPropertyStatementAreRemoved(): void
    {
        $woven = self::convert(
            "<?php\nclass Foo\n{\n    public int \$a = 1, \$b, \$c;\n    public int \$d = [\n        1,\n    ];\n}",
            [AspectContainer::PROPERTY_PREFIX => ['a' => [], 'c' => [], 'd' => []]],
        );

        $this->assertSame(
            "<?php\ntrait FooOriginalTrait\n{\n    public int /* Moved by weaving interceptor to the {@see Foo->a} */ \$b"
            . " /* Moved by weaving interceptor to the {@see Foo->c} */;\n"
            . "    /* Moved by weaving interceptor to the {@see Foo->d} */\n\n\n}",
            $woven,
        );
    }

    public function testInterceptedPromotedPropertyIsDemotedAndAssigned(): void
    {
        $woven = self::convert(
            "<?php\nclass Foo\n{\n    public function __construct(\n        #[\\SensitiveParameter] private readonly int \$x,\n"
            . "        public int \$kept = 0,\n        \$closure = static function () {},\n    ) {\n        echo 1;\n    }\n}",
            [AspectContainer::PROPERTY_PREFIX => ['x' => []]],
        );

        $this->assertSame(
            "<?php\ntrait FooOriginalTrait\n{\n    public function __construct(\n        #[\\SensitiveParameter] int \$x,\n"
            . "        public int \$kept = 0,\n        \$closure = static function () {},\n    ) { \$this->x = \$x;\n        echo 1;\n    }\n}",
            $woven,
        );
    }

    public function testAbstractConstructorGetsNoAssignment(): void
    {
        $woven = self::convert(
            "<?php\nabstract class Foo\n{\n    abstract public function __construct(public int \$x);\n}",
            [AspectContainer::PROPERTY_PREFIX => ['x' => []]],
        );

        $this->assertSame("<?php\ntrait FooOriginalTrait\n{\n    abstract public function __construct(int \$x);\n}", $woven);
    }

    public function testOverrideIsStrippedFromInterceptedMethodsOnly(): void
    {
        $woven = self::convert(
            "<?php\nclass Foo implements \\Countable, \\IteratorAggregate\n{\n"
            . "    #[\\Override, \\Deprecated]\n    public function count(): int { return 0; }\n"
            . "    #[\\Deprecated, \\Override, \\SensitiveParameter]\n    public function getIterator(): \\Iterator { return new \\EmptyIterator(); }\n"
            . "    #[\\Deprecated, \\Override]\n    public static function make(): void {}\n"
            . "    #[\\Override]\n    public function notIntercepted(): void {}\n}",
            [
                AspectContainer::METHOD_PREFIX        => ['count' => [], 'getIterator' => []],
                AspectContainer::STATIC_METHOD_PREFIX => ['make' => []],
            ],
        );

        $this->assertSame(
            "<?php\ntrait FooOriginalTrait   \n{\n"
            . "    #[\\Deprecated]\n    public function count(): int { return 0; }\n"
            . "    #[\\Deprecated, \\SensitiveParameter]\n    public function getIterator(): \\Iterator { return new \\EmptyIterator(); }\n"
            . "    #[\\Deprecated ]\n    public static function make(): void {}\n"
            . "    #[\\Override]\n    public function notIntercepted(): void {}\n}",
            $woven,
        );
    }

    /**
     * Converts the only class-like of the source, returns the woven source
     *
     * @param array<string, array<string, list<string>>> $advices
     */
    private static function convert(string $source, array $advices = []): string
    {
        $stream = fopen('php://input', 'rb');
        assert(is_resource($stream));
        $metadata = new StreamMetaData($stream, $source);
        $class    = new ReflectionFile($metadata->uri, $metadata->syntaxTree)->getFileNamespaces()['']->getClasses();
        $class    = reset($class);
        assert($class !== false);

        new TraitConverter()->convert(
            $class,
            $advices,
            ClassProxyGenerator::indexMethods($class),
            new TokenStreamEditor($metadata),
            $class->getShortName() . AspectContainer::ORIGINAL_TRAIT_SUFFIX,
        );

        return $metadata->source;
    }
}
