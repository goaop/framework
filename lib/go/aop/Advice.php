<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * Base interface for Advice
 *
 * @package go
 * @subpackage aop
 */
interface Advice
{
    /** Before advice */
    const BEFORE = 'before';

    /** Around advice */
    const AROUND = 'around';

    /** After advice */
    const AFTER = 'after';
}
