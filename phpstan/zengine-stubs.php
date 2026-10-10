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

/*
 * Declarations of the lisachenko/z-engine API used by the zengine weaving driver (src/Instrument/ZEngine), for
 * static analysis on a host where the package is not installed: it is an optional dependency, which only installs
 * on the PHP minor its branch targets. PHPStan scans this file (`scanFiles` in phpstan.neon) and uses these
 * declarations when the real classes are not autoloadable; they are never included at runtime.
 *
 * Keep the signatures in sync with the z-engine branch named in docs/zengine-driver.md.
 */

namespace ZEngine {
    final class Core
    {
        public static function isInitialized(): bool {}

        public static function init(): void {}
    }
}

namespace ZEngine\Reflection {
    interface FunctionLikeInterface
    {
        public function getFunctionName(): ?string;

        public function redefine(\Closure|FunctionLikeInterface $newBody, ?string $preserveAs = null): void;
    }

    class ReflectionMethod extends \ReflectionMethod implements FunctionLikeInterface
    {
        public function getFunctionName(): ?string {}

        public function redefine(\Closure|FunctionLikeInterface $newBody, ?string $preserveAs = null): void {}
    }

    class ReflectionClass extends \ReflectionClass
    {
        public static function fromClassTable(string $className): ?self {}

        public function getMethod(string $name): ReflectionMethod {}

        public function addMethod(string $methodName, \Closure|FunctionLikeInterface $method): ReflectionMethod {}
    }
}
