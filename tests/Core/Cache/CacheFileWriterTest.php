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
use RuntimeException;

class CacheFileWriterTest extends TestCase
{
    /**
     * In-memory file system - the writer uses one universal atomic write path,
     * so the virtual driver exercises exactly the production code
     */
    private FileSystem $fileSystem;

    private string $baseDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->fileSystem = FileSystem::mount('cachewritervfs');
        $this->baseDir    = $this->fileSystem->path('/base');
    }

    #[\Override]
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

    public function testCreatesTraversableDirectoriesForReadableFileMode(): void
    {
        $writer   = new CacheFileWriter(0644);
        $fileName = $this->baseDir . '/nested/cache.php';

        $writer->write($fileName, 'content');

        // Read access for a group implies the search bit for the directory, otherwise nobody could enter it
        $this->assertSame(0755, fileperms($this->baseDir . '/nested') & 0777);
        $this->assertSame(0644, fileperms($fileName) & 0777);
    }

    public function testDirectoryModeAddsSearchBitsWhereReadIsGranted(): void
    {
        $this->assertSame(0755, CacheFileWriter::directoryModeFor(0644));
        $this->assertSame(0750, CacheFileWriter::directoryModeFor(0640));
        $this->assertSame(0700, CacheFileWriter::directoryModeFor(0600));
        $this->assertSame(0770, CacheFileWriter::directoryModeFor(0770));
    }

    public function testThrowsWhenDirectoryCanNotBeCreated(): void
    {
        $writer = new CacheFileWriter(0770);
        $writer->write($this->baseDir . '/blocker', 'plain file in the way');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create cache directory');

        $writer->write($this->baseDir . '/blocker/cache.php', 'content');
    }

    public function testThrowsAndCleansUpWhenFileCanNotBeMovedIntoPlace(): void
    {
        $writer = new CacheFileWriter(0770);
        // A directory occupies the target name, so the final rename fails
        mkdir($this->baseDir . '/occupied', 0770, true);

        try {
            $writer->write($this->baseDir . '/occupied', 'content');
            $this->fail('Writing over a directory must fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unable to write cache file', $exception->getMessage());
        }

        // The temporary file is removed again
        $directoryEntries = scandir($this->baseDir);
        $this->assertIsArray($directoryEntries);
        $this->assertSame(['occupied'], array_values(array_diff($directoryEntries, ['.', '..'])));
    }
}
