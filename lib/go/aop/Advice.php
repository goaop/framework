<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * Meta class to support AOP terminology in IDE
 *
 * This meta class describe an action taken by the AOP framework at a particular joinpoint. Different types of advice
 * include "around," "before" and "throws" advice, but at the moment only "around" advice is supported.
 *
 *   Around advice is an advice that surrounds a joinpoint such as a method invocation. This is the most powerful kind
 * of advice. Around advices will perform custom behavior before and after the method invocation. They are responsible
 * for choosing whether to proceed to the joinpoint or to shortcut executing by returning their own return value or
 * throwing an exception.
 *
 * Framework model an advice as an PHP-closure interceptor, maintaining a chain of interceptors "around" the joinpoint:
 *   function($params, Joinpoint $joinPoint) {
 *      echo 'Before action';
 *      // call chain here with Joinpoint->proceed() method
 *      $result = $joinPoint->proceed($params, $joinPoint);
 *      echo 'After action';
 *      return $result;
 *   }
 *
 * @package go
 * @subpackage aop
 */
class Advice {

    /** Before advice */
    const BEFORE = 'before';

    /** Around advice */
    const AROUND = 'around';

    /** After advice */
    const AFTER = 'after';
}
