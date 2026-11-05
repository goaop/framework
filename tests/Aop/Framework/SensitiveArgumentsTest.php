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

namespace Go\Aop\Framework;

use Closure;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Stubs\SensitiveArgumentsTarget;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A #[\SensitiveParameter] argument of an intercepted call must not show up in the frames of the framework (#795)
 *
 * The joinpoints receive the arguments as an array: without the attribute on their own parameters, the secret was
 * listed in Throwable::getTrace() (error trackers serialize it), and constructor joinpoints spread the arguments into
 * ReflectionMethod::invoke(), which printed the secret in the plain text trace as well.
 */
final class SensitiveArgumentsTest extends TestCase
{
    private const string SECRET = 's3cr3t';

    /**
     * @var array<string, string|false> Previous values of the INI settings changed by the test
     */
    private array $iniSettings = [];

    protected function setUp(): void
    {
        // Without arguments in the trace there is nothing to check (php.ini-production turns them off), and the
        // plain text trace must print the strings themselves
        $this->iniSettings = [
            'zend.exception_ignore_args'          => ini_set('zend.exception_ignore_args', '0'),
            'zend.exception_string_param_max_len' => ini_set('zend.exception_string_param_max_len', '15'),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->iniSettings as $name => $value) {
            if ($value !== false) {
                ini_set($name, $value);
            }
        }
    }

    public function testDynamicMethodJoinpointHidesArguments(): void
    {
        $target     = new SensitiveArgumentsTarget();
        $invocation = new DynamicTraitAliasMethodInvocation([$this->around()], SensitiveArgumentsTarget::class, 'login', $target->login(...));

        $this->assertSecretIsHidden(static fn() => $invocation($target, ['bob', self::SECRET]));
    }

    public function testStaticMethodJoinpointHidesArguments(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation(
            [$this->around()],
            SensitiveArgumentsTarget::class,
            'staticLogin',
            SensitiveArgumentsTarget::staticLogin(...),
        );

        $this->assertSecretIsHidden(static fn() => $invocation(SensitiveArgumentsTarget::class, ['bob'], [self::SECRET]));
    }

    public function testFunctionJoinpointHidesArguments(): void
    {
        $invocation = new ReflectionFunctionInvocation([$this->around()], 'strlen', SensitiveArgumentsTarget::staticLogin(...));

        $this->assertSecretIsHidden(static fn() => $invocation(['bob'], [self::SECRET]));
    }

    public function testConstructorJoinpointHidesArguments(): void
    {
        $invocation = new ReflectionConstructorInvocation([$this->around()], SensitiveArgumentsTarget::class);

        $this->assertSecretIsHidden(static fn() => $invocation(['bob', self::SECRET]));
    }

    public function testRewrittenNewExpressionHidesArguments(): void
    {
        // `new SensitiveArgumentsTarget('bob', 's3cr3t')` is rewritten to the magic call
        // `ConstructorExecutionTransformer::getInstance()->{SensitiveArgumentsTarget::class}('bob', 's3cr3t')`
        $this->assertSecretIsHidden(
            static fn() => ConstructorExecutionTransformer::getInstance()->__call(SensitiveArgumentsTarget::class, ['bob', self::SECRET]),
        );
    }

    private function around(): AroundInterceptor
    {
        return new AroundInterceptor(static fn(\Go\Aop\Intercept\Joinpoint $joinpoint): mixed => $joinpoint->proceed());
    }

    /**
     * Runs the call and checks that the secret is in no frame of the framework, nor in the plain text trace
     *
     * Internal functions can not hide their arguments: ReflectionMethod::invokeArgs() and forward_static_call_array()
     * still get the arguments as an array, which the plain text trace prints as `Array`.
     */
    private function assertSecretIsHidden(Closure $call): void
    {
        try {
            $call();
            $this->fail('The target must throw');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString(self::SECRET, $exception->getTraceAsString());
            foreach ($exception->getTrace() as $index => $frame) {
                $function = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
                if (!isset($frame['file']) && !str_starts_with($function, 'Go\\')) {
                    continue; // Called by an internal function: the frame itself is a userland one, checked below
                }
                if (in_array($function, ['ReflectionMethod->invokeArgs', 'forward_static_call_array'], true)) {
                    continue;
                }
                $this->assertFalse(
                    self::containsSecret($frame['args'] ?? []),
                    sprintf('Frame #%d %s() lists the sensitive argument', $index, $function),
                );
            }
        }
    }

    /**
     * @param array<mixed> $arguments
     */
    private static function containsSecret(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === self::SECRET || (is_array($argument) && self::containsSecret($argument))) {
                return true;
            }
        }

        return false;
    }
}
