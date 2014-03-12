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
