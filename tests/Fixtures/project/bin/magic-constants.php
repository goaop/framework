<?php
declare(strict_types=1);

/**
 * Boots the fixture project and prints, as JSON, what the magic constants of an unwoven class and of a file
 * included from a woven class resolve to (issue #679)
 */

use Go\Tests\TestProject\Application\MagicConstantHolder;
use Go\Tests\TestProject\Application\MagicConstantIncluder;

include __DIR__ . '/../web/index.php';

ob_start();
$includedPaths = new MagicConstantIncluder()->doSomething();
ob_end_clean();

echo json_encode([
    'holder'   => new MagicConstantHolder()->paths(),
    'included' => $includedPaths,
], JSON_THROW_ON_ERROR);
