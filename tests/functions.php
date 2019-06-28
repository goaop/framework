<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */
namespace Symfony\Component\Finder;

/**
 * This helper function overrides the PHP glob() function so it is able to be run with virtual file system,
 * which is supported by Webmozart\Glob\Glob
 *
 * @param      $pattern
 * @param null $flags
 *
 * @return string[]
 */
function glob($pattern, $flags = null) {
    return \Webmozart\Glob\Glob::glob($pattern, $flags);
}
