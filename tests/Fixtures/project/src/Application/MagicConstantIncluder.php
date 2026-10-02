<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Woven class (DoSomethingAspect) including a plain file
 */
class MagicConstantIncluder
{
    /**
     * @return array{dir: string, file: string}
     */
    public function doSomething(): array
    {
        return require __DIR__ . '/magic-constant-paths.php';
    }
}
