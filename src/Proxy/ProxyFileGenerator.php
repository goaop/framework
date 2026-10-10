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

namespace Go\Proxy;

use Go\Proxy\Generator\GeneratedCodePrinter;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Generates the cache file holding the proxies of all woven class-likes declared in one source file.
 *
 * A single proxy is emitted exactly like before (`namespace X;` + imports + declaration). Several proxies are emitted
 * as one braced namespace block per proxy (`namespace X { ... }`, `namespace { ... }` for the global namespace):
 * every proxy keeps its own imports, which could collide when merged into one block of the same namespace.
 *
 * @internal
 */
final class ProxyFileGenerator
{
    private static ?Standard $printer = null;

    /**
     * Proxy generators with the namespace of their class, in the order they are emitted
     *
     * @var list<array{ClassProxyGenerator, string}>
     */
    private array $proxies = [];

    /**
     * @param bool $useStrictMode If the source file used strict mode, the proxies should too
     */
    public function __construct(private readonly bool $useStrictMode) {}

    /**
     * Adds a proxy to the file, proxies are emitted in the order they are added
     *
     * @param string $namespaceName Namespace of the proxied class, an empty string for the global namespace
     */
    public function addProxy(ClassProxyGenerator $generator, string $namespaceName): void
    {
        $this->proxies[] = [$generator, $namespaceName];
    }

    /**
     * Generates the full PHP source of the proxy file
     */
    public function generate(): string
    {
        $code = '<?php' . PHP_EOL;
        if ($this->useStrictMode) {
            $code .= 'declare(strict_types=1);' . PHP_EOL;
        }

        if (count($this->proxies) === 1) {
            return $code . $this->proxies[0][0]->generate();
        }

        $blocks = [];
        foreach ($this->proxies as [$generator, $namespaceName]) {
            $block    = new Namespace_($namespaceName !== '' ? new Name($namespaceName) : null, $generator->generateStmts());
            $blocks[] = self::getPrinter()->prettyPrint([$block]);
        }

        // Blocks are separated by a blank line to keep the file readable
        return $code . implode("\n\n", $blocks);
    }

    private static function getPrinter(): Standard
    {
        return self::$printer ??= new GeneratedCodePrinter(['shortArraySyntax' => true, 'bracedNamespaces' => true]);
    }
}
