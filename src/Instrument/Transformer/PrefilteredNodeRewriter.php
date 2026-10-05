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

namespace Go\Instrument\Transformer;

/**
 * Rule whose nodes can only occur in a source that contains one of its markers
 *
 * {@see SyntaxTreeRewriter} looks the markers up in the source before the syntax tree walk: a rule is skipped for a
 * file without any of them, and the walk is skipped entirely when no rule is left. Markers are compared
 * case-insensitively, like PHP keywords, magic constants and function names. A rule that can not name markers
 * implements {@see NodeRewriter} only and sees every file.
 */
interface PrefilteredNodeRewriter extends NodeRewriter
{
    /**
     * Substrings of which a source contains at least one if the rule can rewrite a node of it
     *
     * @return non-empty-list<non-empty-string>
     */
    public function getSourceMarkers(): array;
}
