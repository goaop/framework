<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

/**
 * @see https://php.watch/versions/8.1/readonly
 */
class ClassWithPhp81ReadOnlyProperties
{
    public readonly int $publicReadonlyInt;

    protected readonly array $protectedReadonlyArray;

    private readonly object $privateReadonlyObject;
}

/**
 * @see https://php.watch/versions/8.1/enums
 */
enum SimplePhp81EnumWithSuit {
    case Clubs;
    case Diamonds;
    case Hearts;
    case Spades;
}

/**
 * @see https://php.watch/versions/8.1/enums#enums-backed
 */
enum BackedPhp81EnumHTTPMethods: string
{
    case GET = 'get';
    case POST = 'post';
}

/**
 * @see https://php.watch/versions/8.1/enums#enum-methods
 */
enum BackedPhp81EnumHTTPStatusWithMethod: int
{
    case OK = 200;
    case ACCESS_DENIED = 403;
    case NOT_FOUND = 404;

    public function label(): string {
        return static::getLabel($this);
    }

    public static function getLabel(self $value): string {
        return match ($value) {
            self::OK => 'OK',
            self::ACCESS_DENIED => 'Access Denied',
            self::NOT_FOUND => 'Page Not Found',
        };
    }
}

/**
 * @see https://php.watch/versions/8.1/intersection-types
 */
class ClassWithPhp81IntersectionType implements \Countable
{
    private \Iterator&\Countable $countableIterator;

    public function __construct(\Iterator&\Countable $countableIterator)
    {
        $this->countableIterator = $countableIterator;
    }

    public function count(): int
    {
        return count($this->countableIterator);
    }
}

/**
 * @see https://php.watch/versions/8.1/intersection-types
 */
function functionWithPhp81IntersectionType(\Iterator&\Countable $value): \Iterator&\Countable {
    foreach($value as $val) {}
    count($value);

    return $value;
}

/**
 * @see https://php.watch/versions/8.1/never-return-type
 */
class ClassWithPhp81NeverReturnType
{
    public static function doThis(): never
    {
        throw new \RuntimeException('Not implemented');
    }
}

/**
 * @see https://php.watch/versions/8.1/never-return-type
 */
function functionWithPhp81NeverReturnType(): never
{
    throw new \RuntimeException('Not implemented');
}

/**
 * @see https://php.watch/versions/8.1/final-class-const
 */
class ClassWithPhp81FinalClassConst {
    final public const TEST = '1';
}
