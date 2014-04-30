<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument;

use TokenReflection\Broker\Backend\Memory;
use TokenReflection\ReflectionFile;
use TokenReflection\Stream\StreamBase;

/**
 * Special memory backend that keep token stream only for one file
 */
class CleanableMemory extends Memory
{
    /**
     * Nested level to track hierarchy
     *
     * @var int
     */
    protected static $level = 0;

    /**
     * Increments current level
     */
    public static function enterProcessing()
    {
        self::$level++;
    }

    /**
     * Decrements current level
     */
    public static function leaveProcessing()
    {
        self::$level = self::$level > 0 ? self::$level-1 : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function addFile(StreamBase $tokenStream, ReflectionFile $file)
    {
        if (self::$level <= 1) {
            // Clean the old cache only for main classes, allow to bypass cleaning for hierarchy
            $this->clearTokenCache();
        }

        return parent::addFile($tokenStream, $file);
    }

    /**
     * Clear token stream cache from parent object
     */
    private function clearTokenCache()
    {
        static $tokenStreams = null;
        if (!$tokenStreams) {
            $tokenStreams = new \ReflectionProperty(get_parent_class(), 'tokenStreams');
            $tokenStreams->setAccessible(true);
        }
        $tokenStreams->setValue($this, array());
    }
}
