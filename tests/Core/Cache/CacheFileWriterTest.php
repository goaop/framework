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
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/goaop-cache-file-writer-' . uniqid();
    }

    protected function tearDown(): void
    {
        // Guard the cleanup: deletions must stay inside the uniquely-named temp
        // directory this test created, whatever happens to the property value
        $this->assertStringStartsWith(sys_get_temp_dir() . '/goaop-cache-file-writer-', $this->baseDir);
        if (is_dir($this->baseDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->baseDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $fileInfo) {
                assert($fileInfo instanceof \SplFileInfo);
                $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
            }
            rmdir($this->baseDir);
        }
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

        $this->assertSame([$fileName], glob($this->baseDir . '/*'));
    }

    public function testWritesToStreamWrapperPath(): void
    {
        $fileSystem = FileSystem::mount('cachewritervfs');
        try {
            $writer   = new CacheFileWriter(0770);
            $fileName = 'cachewritervfs://cache/file.php';
            $fileSystem->createDirectory('/cache', recursive: true);

            $writer->write($fileName, 'stream content');

            $this->assertSame('stream content', file_get_contents($fileName));
        } finally {
            $fileSystem->unmount();
        }
    }
}
