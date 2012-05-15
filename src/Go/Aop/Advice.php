<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

/**
 * Tag interface for Advice. Implementations can be any type of advice, such as Interceptors.
 *
 * @author Rod Johnson
 * @author Lissachenko Alexander
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
