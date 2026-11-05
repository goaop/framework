<?php
declare(strict_types=1);

/**
 * Boots the fixture project, includes files from a woven class and prints, as JSON, what the included files
 * returned, which files were included and what the intercepted includes are rewritten to (issue #746)
 */

use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Tests\TestProject\Application\FileIncluder;

include __DIR__ . '/../web/index.php';

$result = new FileIncluder()->includeFiles();
// The included files list the resource itself for a `php://filter` include, so the rewritten paths are reported too
$rewrites = [];
foreach (['plain-file.php', 'woven-file.php'] as $fileName) {
    $rewrites[$fileName] = FilterInjectorTransformer::rewrite($fileName, __DIR__ . '/../src/Application/includes');
}

// Serving known files natively must not register the stream filter (issue #824); an unknown file still needs it
$includedFiles    = get_included_files();
$filterId         = SourceTransformingLoader::FILTER_IDENTIFIER;
$filterBeforeMiss = in_array($filterId, stream_get_filters(), true);
$unknownRewrite   = FilterInjectorTransformer::rewrite(__DIR__ . '/../src/Application/includes/unknown-file.php');
$filterAfterMiss  = in_array($filterId, stream_get_filters(), true);

echo json_encode(
    [
        'result'           => $result,
        'includedFiles'    => $includedFiles,
        'rewrites'         => $rewrites,
        'filterBeforeMiss' => $filterBeforeMiss,
        'unknownRewrite'   => $unknownRewrite,
        'filterAfterMiss'  => $filterAfterMiss,
    ],
    JSON_THROW_ON_ERROR,
);
