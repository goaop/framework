<?php
declare(strict_types=1);

/**
 * Boots the fixture project, includes files from a woven class and prints, as JSON, what the included files
 * returned, which files were included and what the intercepted includes are rewritten to (issue #746)
 */

use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Tests\TestProject\Application\FileIncluder;

include __DIR__ . '/../web/index.php';

$result = new FileIncluder()->includeFiles();
// The included files list the resource itself for a `php://filter` include, so the rewritten paths are reported too
$rewrites = [];
foreach (['plain-file.php', 'woven-file.php'] as $fileName) {
    $rewrites[$fileName] = FilterInjectorTransformer::rewrite($fileName, __DIR__ . '/../src/Application/includes');
}

echo json_encode(
    ['result' => $result, 'includedFiles' => get_included_files(), 'rewrites' => $rewrites],
    JSON_THROW_ON_ERROR,
);
