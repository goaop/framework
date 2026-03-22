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

namespace Go\Proxy\Generator\Stubs;

class MethodGeneratorTestStub
{
    public function publicMethod(string $name, int $count = 0): string
    {
        return str_repeat($name, $count);
    }

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}

    public static function staticMethod(array $data): array
    {
        return $data;
    }

    final public function finalMethod(): void {}

    public function methodWithException(\Exception $ex): ?\Exception
    {
        return $ex;
    }

    #[\Deprecated]
    public function deprecatedMethod(): void {}

    public function methodWithSensitiveParam(#[\SensitiveParameter] string $secret): void {}
}
