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
 * Page of the demo, rendered by index.php. Presentation only: styles are in assets/demo.css,
 * behaviour (AOP switch, source tabs, mobile navigation, code highlighting) is in assets/demo.js.
 */

use Demo\Showcase\Showcase;
use Demo\Showcase\ShowcaseCatalog;
use Demo\Showcase\ShowcaseResult;

/** @var ShowcaseCatalog $catalog */
/** @var Showcase|null $showcase */
/** @var ShowcaseResult|null $result */
/** @var bool $isAopEnabled */
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title><?= $showcase !== null ? htmlspecialchars($showcase->title) . ' · ' : '' ?>Go! AOP Demo</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%237c5cff'/%3E%3Ctext x='16' y='22' font-family='Arial' font-weight='700' font-size='15' fill='white' text-anchor='middle'%3EGo!%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/demo.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"
            integrity="sha384-RH2xi4eIQ/gjtbs9fUXM68sLSi99C7ZWBRX1vDrVv6GQXRibxXLbwO2NGZB74MbU" crossorigin="anonymous" defer></script>
    <script src="assets/demo.js" defer></script>
</head>
<body>

<div class="backdrop" aria-hidden="true"></div>

<header class="topbar">
    <button type="button" class="icon-button menu-button" aria-controls="sidebar" aria-expanded="false" aria-label="Open the list of examples" data-menu-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <a class="brand" href="./">
        <span class="brand-mark">Go!</span>
        <span class="brand-name">AOP <span class="brand-tag">demo</span></span>
    </a>

    <nav class="topbar-links" aria-label="Project">
        <a href="https://github.com/goaop/framework/tree/master/docs" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h10l4 4v12H5z M15 4v4h4 M8 12h8 M8 16h8"/></svg>
            <span>Docs</span>
        </a>
        <a href="https://github.com/goaop/framework" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 19c-4 1.5-4-2-6-2.5m12 4.5v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 9.5c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/></svg>
            <span>GitHub</span>
        </a>
    </nav>

    <form class="aop-switch" method="post" data-aop-switch>
        <input type="hidden" name="aop" value="<?= $isAopEnabled ? 'off' : 'on' ?>">
        <button type="submit" role="switch" aria-checked="<?= $isAopEnabled ? 'true' : 'false' ?>"
                title="<?= $isAopEnabled ? 'Run the examples without aspects' : 'Weave the aspects into the examples' ?>">
            <span class="aop-switch-label">AOP</span>
            <span class="aop-switch-track"><span class="aop-switch-thumb"></span></span>
            <span class="aop-switch-state"><?= $isAopEnabled ? 'On' : 'Off' ?></span>
        </button>
    </form>
</header>

<div class="layout">

    <aside class="sidebar" id="sidebar">
        <nav aria-label="Examples">
            <?php foreach ($catalog->groups() as $group => $items): ?>
                <h2 class="nav-group"><?= htmlspecialchars($group) ?></h2>
                <ul class="nav-list">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a class="nav-link<?= $item === $showcase ? ' is-active' : '' ?>" href="?showcase=<?= urlencode($item->id) ?>"
                               <?= $item === $showcase ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($item->title) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <div class="resources">
            <details>
                <summary>AOP solutions</summary>
                <ul>
                    <li><a href="https://github.com/php-deal/framework" target="_blank" rel="noopener">PhpDeal — Design by Contract</a></li>
                    <li><a href="https://github.com/Codeception/AspectMock" target="_blank" rel="noopener">AspectMock — testing framework</a></li>
                    <li><a href="https://github.com/lisachenko/warlock" target="_blank" rel="noopener">Warlock — Go! AOP + Symfony DiC</a></li>
                </ul>
            </details>
            <details>
                <summary>Videos &amp; slides</summary>
                <ul>
                    <li><a href="https://www.youtube.com/watch?v=aZ_9PuHemBk&amp;t=1283" target="_blank" rel="noopener">Advanced logging in PHP</a></li>
                    <li><a href="https://www.youtube.com/watch?v=BXKQ99-78bI" target="_blank" rel="noopener">Aspect-Oriented Programming in PHP</a></li>
                    <li><a href="https://www.slideshare.net/lisachenko/solving-crosscutting-concerns-in-php" target="_blank" rel="noopener">Solving Cross-Cutting Concerns in PHP</a></li>
                    <li><a href="https://www.slideshare.net/lisachenko/weaving-aspects-in-php-with-the-help-of-go-aop-library" target="_blank" rel="noopener">Weaving aspects in PHP with Go! AOP</a></li>
                    <li><a href="https://www.slideshare.net/lisachenko/aspect-oriented-programming-in-php" target="_blank" rel="noopener">Aspect-Oriented Programming in PHP (Russian)</a></li>
                </ul>
            </details>
        </div>
    </aside>

    <div class="scrim" data-menu-close></div>

    <main class="content">
        <?php if ($showcase === null || $result === null): ?>

            <section class="hero">
                <p class="eyebrow">Aspect-oriented programming for PHP</p>
                <h1>Cross-cutting concerns, <span class="gradient-text">woven in</span> at load time.</h1>
                <p class="lead">
                    Each example runs plain PHP classes. The aspects add logging, caching, error reporting and more
                    without touching their code. Flip the <strong>AOP</strong> switch to run the same code without them.
                </p>
                <div class="hero-actions">
                    <a class="button button-primary" href="?showcase=<?= urlencode($catalog->all()[0]->id) ?>">Start with the first example</a>
                    <a class="button button-ghost" href="https://github.com/goaop/framework" target="_blank" rel="noopener">Star on GitHub</a>
                </div>
            </section>

            <?php foreach ($catalog->groups() as $group => $items): ?>
                <section class="card-group">
                    <h2 class="section-title"><?= htmlspecialchars($group) ?></h2>
                    <div class="card-grid">
                        <?php foreach ($items as $index => $item): ?>
                            <a class="card" href="?showcase=<?= urlencode($item->id) ?>" style="--i: <?= $index ?>">
                                <h3><?= htmlspecialchars($item->title) ?></h3>
                                <p><?= htmlspecialchars($item->summary) ?></p>
                                <span class="card-link">Run example <span aria-hidden="true">→</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

        <?php else: ?>

            <section class="showcase-header">
                <p class="eyebrow"><?= htmlspecialchars($showcase->group) ?></p>
                <h1><?= htmlspecialchars($showcase->title) ?></h1>
                <p class="lead"><?= htmlspecialchars($showcase->summary) ?></p>
                <ul class="chips">
                    <li class="chip"><span class="chip-key">Aspect</span><?= htmlspecialchars($showcase->aspectName()) ?></li>
                    <li class="chip <?= $isAopEnabled ? 'chip-on' : 'chip-off' ?>"><span class="chip-key">AOP</span><?= $isAopEnabled ? 'enabled' : 'disabled' ?></li>
                    <li class="chip"><span class="chip-key">Time</span><?= number_format($result->elapsedMs, 2) ?> ms</li>
                </ul>
            </section>

            <?php if (!$isAopEnabled): ?>
                <p class="callout">
                    AOP is switched off: the example runs exactly as written, without its aspect.
                    Compare the output with the woven version by switching it on again.
                </p>
            <?php endif; ?>

            <section class="panel terminal" aria-labelledby="output-title">
                <header class="panel-header">
                    <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    <h2 id="output-title">Output</h2>
                    <button type="button" class="copy-button" data-copy="#output">Copy</button>
                </header>
                <pre id="output" class="terminal-body"><?= $result->output !== '' ? htmlspecialchars($result->output) : '<span class="muted">The example printed nothing.</span>' ?></pre>
            </section>

            <section class="panel sources" data-tabs>
                <div class="tabs" role="tablist" aria-label="Source code">
                    <?php foreach (array_keys($result->sources()) as $index => $label): ?>
                        <button type="button" role="tab" id="tab-<?= $index ?>" aria-controls="source-<?= $index ?>"
                                aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"><?= htmlspecialchars($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach (array_values($result->sources()) as $index => $source): ?>
                    <div class="tab-panel" role="tabpanel" id="source-<?= $index ?>" aria-labelledby="tab-<?= $index ?>">
                        <div class="file-bar">
                            <span class="file-name"><?= htmlspecialchars($source->fileName) ?></span>
                            <button type="button" class="copy-button" data-copy="#code-<?= $index ?>">Copy</button>
                        </div>
                        <pre class="code"><code id="code-<?= $index ?>" class="language-php"><?= htmlspecialchars($source->code) ?></code></pre>
                    </div>
                <?php endforeach; ?>
            </section>

        <?php endif; ?>

        <footer class="footer">
            Go! AOP framework · <a href="https://go.aopphp.com/" target="_blank" rel="noopener">go.aopphp.com</a>
            · You can also run this code with Xdebug.
        </footer>
    </main>
</div>

</body>
</html>
