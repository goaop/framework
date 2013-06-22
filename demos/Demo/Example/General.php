<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Demo\Example;

use Demo\Annotation\Cacheable;

/**
 * Example class to test aspects
 */
class General
{

    /**
     * Message for example
     *
     * @var string
     */
    protected $message = '';

    /**
     * Public message to read/write
     *
     * @var string
     */
    public $publicMessage = '';

    /**
     * Constructs example class
     *
     * @param string $message Message to show
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Protected method that shows hello
     */
    protected function protectedHello()
    {
        echo 'Hello, you have a protected message: ', $this->message, "<br>", PHP_EOL;
    }

    /**
     * Public method that shows hello
     */
    public function publicHello()
    {
        echo 'Hello, you have a public message: ', $this->message, "<br>", PHP_EOL;
    }

    /**
     * Public method to test interception of protected method
     */
    public function runProtectedHello()
    {
        echo 'Calling protected method...', "<br>", PHP_EOL;
        $this->protectedHello();
    }

    /**
     * Protected method to return the value of message
     *
     * @return string
     */
    protected function getProtectedMessage()
    {
        return $this->message;
    }

    /**
     * Executes a protected method to read the message and show it
     */
    public function showProtectedMessage()
    {
        echo 'Getting value from protected method...', "<br>", PHP_EOL;
        $message = $this->getProtectedMessage();
        echo 'Hello, have you read that message: ', $message, "<br>", PHP_EOL;
    }

    /**
     * Test function for static method inter
     *
     * @param string $message Message to show
     */
    public static function showStaticMessage($message)
    {
        echo "Static message is: ", $message, "<br>", PHP_EOL;
        echo "Scope is: ", get_called_class(), "<br>", PHP_EOL;
    }

    /**
     * Test function for recursive calls
     *
     * @param integer $level Level of recursion
     */
    public function testRecursion($level)
    {
        echo "Recursive call, level is: ", $level, "<br>", PHP_EOL;
        if ($level > 0) {
            $this->testRecursion($level - 1);
        }
    }

    /**
     * Test cacheable by annotation
     *
     * @Cacheable
     * @param float $timeToSleep Amount of time to sleep
     *
     * @return string
     */
    public function cacheMe($timeToSleep)
    {
        usleep($timeToSleep * 1e6);
        return 'Yeah';
    }
}
