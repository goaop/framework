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

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
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
     *
     * @param string $name                  Element name to match, can contain wildcards **,*,?,|
     * @param bool   $useContextForMatching Switch to matching context name instead of reflector
     */
    public function __construct(
        private int    $pointcutKind,
        private string $name,
        private bool   $useContextForMatching = false,
    ) {
        // Special parenthesis is needed for stricter matching, see https://github.com/goaop/framework/issues/115
        $this->regexp = '/^(' . strtr(
            preg_quote($this->name, '/'),
            [
                '\\*'    => '[^\\\\]+?',
                '\\*\\*' => '.+?',
                '\\?'    => '.',
                '\\|'    => '|'
            ]
        ) . ')$/';
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
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
        return ($instanceToMatch->getName() === $this->name) || preg_match($this->regexp, $instanceToMatch->getName());
    }

    public function getKind(): int
    {
        return $this->pointcutKind;
    }
}
