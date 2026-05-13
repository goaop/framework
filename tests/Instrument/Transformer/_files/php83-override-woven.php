<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.3 — class with #[\Override] on an intercepted method.
 * WeavingTransformer must strip the attribute from the generated trait so that
 * the proxy's __aop__overriddenMethod alias does not trigger a fatal error.
 */
trait TestClassWithOverride__AopProxied
{
    public function overriddenMethod(): string
    {
        return 'child';
    }

    public function normalMethod(): int
    {
        return 42;
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php83-override.php';
