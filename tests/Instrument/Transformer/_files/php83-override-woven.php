<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.3 — class with #[\Override] on an intercepted method.
 * WeavingTransformer must strip the attribute from the generated trait so that
 * the proxy's overriddenMethodOriginal alias does not trigger a fatal error.
 */
trait TestClassWithOverrideOriginal
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
