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

namespace Go\Core\Cache;

use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\TestCase;

class CacheFileWriterTest extends TestCase
{
    /**
     * In-memory file system - the writer uses one universal atomic write path,
     * so the virtual driver exercises exactly the production code
     */
    private FileSystem $fileSystem;

    private string $baseDir;

    protected function setUp(): void
    {
        $this->fileSystem = FileSystem::mount('cachewritervfs');
        $this->baseDir    = $this->fileSystem->path('/base');
    }

    protected function tearDown(): void
    {
        $this->fileSystem->unmount();
    }

    public function testWritesContentCreatingMissingDirectories(): void
    {
        $writer   = new CacheFileWriter(0770);
        $fileName = $this->baseDir . '/deeply/nested/cache.php';

        $writer->write($fileName, '<?php return 42;');

        $this->assertFileExists($fileName);
        $this->assertSame('<?php return 42;', file_get_contents($fileName));
    }

    public function testStripsExecutableBitsFromWrittenFile(): void
    {
        $writer   = new CacheFileWriter(0777);
        $fileName = $this->baseDir . '/cache.php';

        $writer->write($fileName, 'content');

        $this->assertSame(0666, fileperms($fileName) & 0777);
    }

    public function testOverwritesExistingFile(): void
    {
        $writer   = new CacheFileWriter(0770);
        $fileName = $this->baseDir . '/cache.php';

        $writer->write($fileName, 'first');
        $writer->write($fileName, 'second');

        $this->assertSame('second', file_get_contents($fileName));
    }

    public function testLeavesNoTemporaryFilesBehind(): void
    {
        $writer   = new CacheFileWriter(0770);
        $fileName = $this->baseDir . '/cache.php';

        $writer->write($fileName, 'content');

        // The atomic tmp+rename write must leave only the target file in the directory
        $directoryEntries = scandir($this->baseDir);
        $this->assertIsArray($directoryEntries);
        $this->assertSame(['cache.php'], array_values(array_diff($directoryEntries, ['.', '..'])));
    }
}
