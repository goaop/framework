<?php
/**
 * Go! AOP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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
     * {@inheritDoc}
     */
    public function addFile(StreamBase $tokenStream, ReflectionFile $file)
    {
        $this->clearTokenCache();
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
