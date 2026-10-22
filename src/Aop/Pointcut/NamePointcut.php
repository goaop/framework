<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Pointcut;
use Go\Core\Cache\AdvisorCacheCompiler;
use Go\ParserReflection\ReflectionFileNamespace;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * General name pointcut checks element name to match it
 */
final readonly class NamePointcut implements Pointcut
{
    /**
     * Regular expression for pattern matching
     */
    private string $regexp;

    /**
     * Name matcher constructor
     */
    public function __construct(
        public int $kind,
        /** Element name to match, can contain wildcards `**`, `*` and `|`, see compilePattern() */
        private string $name,
        /** Switch to matching context name instead of reflector */
        private bool $useContextForMatching = false,
    ) {
        $this->regexp = self::compilePattern($this->name);
    }

    /**
     * Translates a name pattern into a regular expression, one name part (a segment between namespace separators)
     * at a time:
     *  - `*` is any characters, including none, inside the name part, it never crosses a namespace separator;
     *  - `**` is any characters, including namespace separators (at least one);
     *  - `|` separates alternatives of the name part it is written in, so `Ns\A|B` is `Ns\A` or `Ns\B`, never a
     *    global class `B`. The group around the alternatives keeps the anchors strict for each of them, see
     *    https://github.com/goaop/framework/issues/115
     */
    private static function compilePattern(string $pattern): string
    {
        $nameParts = [];
        foreach (explode('\\', $pattern) as $namePart) {
            $regexp = strtr(
                preg_quote($namePart, '/'),
                [
                    '\\*'    => '[^\\\\]*?',
                    '\\*\\*' => '.+?',
                    '\\|'    => '|',
                ],
            );
            $nameParts[] = str_contains($namePart, '|') ? "(?:{$regexp})" : $regexp;
        }

        return '/^' . implode('\\\\', $nameParts) . '$/';
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        // Let's determine what will be used for matching - context or reflector
        if ($this->useContextForMatching) {
            $instanceToMatch = $context;
        } elseif (!isset($reflector)) {
            // Without context matching flag we should always match to get an instance of reflector
            return true;
        } else {
            $instanceToMatch = $reflector;
        }

        // Perform static check to ensure that we match our name statically
        $name = $instanceToMatch->getName();
        if ($name === $this->name) {
            return true;
        }
        $isMatched = preg_match($this->regexp, $name);
        if ($isMatched === false) {
            throw PointcutSyntaxException::namePatternNotMatchable($this->name, preg_last_error_msg());
        }

        return $isMatched === 1;
    }

    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(self::class), AdvisorCacheCompiler::compileArgs([
            ['kind', new Int_($this->kind), false],
            ['name', new String_($this->name), false],
            ['useContextForMatching', new ConstFetch(new Name('true')), !$this->useContextForMatching],
        ]));
    }
}
