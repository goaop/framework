<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

class Example
{
    /**
     * Message for example
     *
     * @var string
     */
    protected $message = '';

    /**
     * Hello, aspect!
     *
     * @param string $message Message for hello
     */
    public function hello($message)
    {
        $this->message = $message;
        echo $this->message, "<br>\n";
        static::show($this->message);
    }

    /**
     * Test function for static method inter
     *
     * @param $message
     */
    public static function show($message)
    {
        echo "Static call! $message", "<br>\n";
        echo "Scope is: ", get_called_class(), "<br>\n";
    }
}
