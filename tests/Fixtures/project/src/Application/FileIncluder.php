<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Class including plain files, woven by Features::INTERCEPT_INCLUDES only (issue #746)
 */
class FileIncluder
{
    /**
     * @return array<string, mixed>
     */
    public function includeFiles(): array
    {
        return [
            'plain' => require __DIR__ . '/includes/plain-file.php',
            'woven' => require __DIR__ . '/includes/woven-file.php',
        ];
    }
}
