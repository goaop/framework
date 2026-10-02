<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Class that is never woven: its magic constants must resolve to the original location
 */
class MagicConstantHolder
{
    /**
     * @return array{dir: string, file: string}
     */
    public function paths(): array
    {
        return ['dir' => __DIR__, 'file' => __FILE__];
    }
}
