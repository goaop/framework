<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

use Attribute;
use Go\ParserReflection\{ReflectionMethod, ReflectionProperty as P};

class ClassWithPhp80Features
{
    public function acceptsStringArrayDefaultToNull(array|string $iterable = null) : array {}
}

/**
 * @see https://php.watch/versions/8.0/named-parameters
 */
class ClassWithPHP80NamedCall
{
    public static function foo(string $key1 = '', string $key2 = ''): string
    {
        return $key1 . ':' . $key2;
    }

    public static function namedCall(): array
    {
        return [
            'key1'        => self::foo(key1: 'bar'),
            'key2'        => self::foo(key2: 'baz'),
            'keys'        => self::foo(key1: 'A', key2: 'B'),
            'reverseKeys' => self::foo(key2: 'A', key1: 'B'),
            'unpack'      => self::foo(...['key1' => 'C', 'key2' => 'D']),
        ];
    }
}

/**
 * @see https://php.watch/versions/8.0/attributes
 */
#[Attribute(Attribute::TARGET_ALL | Attribute::IS_REPEATABLE)]
readonly class ClassPHP80Attribute
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

/**
 * @see https://php.watch/versions/8.0/attributes
 */
#[ClassPHP80Attribute('class')]
class ClassPHP80WithAttribute
{
    #[ClassPHP80Attribute('first')]
    #[ClassPHP80Attribute('second')]
    public const PUBLIC_CONST = 1;

    #[ClassPHP80Attribute('property')]
    private string $privateProperty = 'foo';

    #[ClassPHP80Attribute('method')]
    public function bar(#[ClassPHP80Attribute('parameter')] $parameter)
    {}
}

/**
 * @see https://php.watch/versions/8.0/constructor-property-promotion
 */
class ClassPHP80WithPropertyPromotion
{
    public function __construct(
        private string $privateStringValue,
        private $privateNonTypedValue,
        protected int $protectedIntValue = 42,
        public array $publicArrayValue = [M_PI, M_E],
    ) {}
}

/**
 * @see https://php.watch/versions/8.0/union-types
 */
class ClassWithPHP80UnionTypes
{
    public string|int|float|bool $scalarValue;

    public array|object|null $complexValueOrNull = null;

    /**
     * Special case, internally iterable should be replaced with Traversable|array
     */
    public iterable|object $iterableOrObject;

    public static function returnsUnionType(): object|array|null {}

    public static function acceptsUnionType(\stdClass|\Traversable|array $iterable): void {}
}

/**
 * @see https://php.watch/versions/8.0/mixed-type
 */
class ClassWithPHP80MixedType
{
    public mixed $someMixedPublicProperty;

    public static function returnsMixed(): mixed {}

    public static function acceptsMixed(mixed $value): void {}
}

/**
 * @see https://php.watch/versions/8.0/static-return-type
 */
class ClassWithPHP80StaticReturnType
{
    public static function create(): static {}
}