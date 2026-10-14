<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/*
 * Front controller of the demo: runs the selected showcase and renders the page from templates/page.php,
 * the styles and scripts of the page live in assets/.
 */

use Demo\Showcase\ShowcaseCatalog;

const AOP_COOKIE = 'aop';

// The AOP switch posts the new state: it is remembered in a cookie, then the page is reloaded with GET
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['aop'])) {
    $scriptName = is_string($_SERVER['SCRIPT_NAME'] ?? null) ? $_SERVER['SCRIPT_NAME'] : '/';
    $requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : './';
    setcookie(AOP_COOKIE, $_POST['aop'] === 'off' ? 'off' : 'on', [
        'expires'  => time() + 30 * 86400,
        'path'     => rtrim(dirname($scriptName), '/') . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: ' . $requestUri, true, 303);

    return;
}

$isAopEnabled = ($_COOKIE[AOP_COOKIE] ?? 'on') !== 'off';
require __DIR__ . ($isAopEnabled ? '/autoload_aspect.php' : '/../vendor/autoload.php');

$catalog  = new ShowcaseCatalog();
$showcase = $catalog->find(is_string($_GET['showcase'] ?? null) ? $_GET['showcase'] : '');
$result   = $showcase?->run();

require __DIR__ . '/templates/page.php';
