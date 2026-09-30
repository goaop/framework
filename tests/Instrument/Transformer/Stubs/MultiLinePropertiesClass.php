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

namespace Go\Instrument\Transformer\Stubs;

use Go\Stubs\RichAttr;
use Go\Stubs\StubAttribute;

/**
 * Intercepted property declarations spanning several lines or sharing one statement (issue #669)
 */
class MultiLinePropertiesClass
{
    public array $list = [
        'first',
        'second',
    ];

    public string $text = <<<TEXT
        multi
        line
        TEXT;

    #[StubAttribute('own-line')]
    public int $attributed = 1;

    #[StubAttribute('first')]
    #[RichAttr]
    protected ?string $twoGroups = null;

    public int $keptA = 1, $movedB = 2, $keptC = 3;

    private int $movedD = 4, $movedE = 5;

    public int $movedF = 6,
        $keptG = 7;

    public function marker(): int
    {
        return __LINE__;
    }
}
