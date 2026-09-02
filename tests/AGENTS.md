# tests — Test conventions

## Structure
- Test categories mirror src/ namespaces (e.g. tests/Core/ for src/Core/, tests/Proxy/ for src/Proxy/).
- Functional/integration: tests/Functional/
- General test stubs (weaving-input classes, plus generator reflection targets under tests/Stubs/Generator/ as Go\Stubs\Generator): tests/Stubs/
- Transformer-test stubs (version-specific weaving inputs): tests/Instrument/Transformer/Stubs/
- Fixture project (autoloaded as Go\Tests\TestProject\): tests/Fixtures/project/src/
- Snapshot fixtures: tests/Instrument/Transformer/_files/ (*-woven.php trait, *-proxy.php proxy)

## File system in tests — ALWAYS the virtual driver
- Any test touching the file system uses goaop/virtual-file-system (`Go\VirtualFileSystem\FileSystem`), NEVER sys_get_temp_dir()/tempnam()/real disk — no /tmp pollution, no manual cleanup.
- Pattern: `$fs = FileSystem::mount('<unique-scheme>')` in setUp (or a try/finally), build paths via `$fs->path('/...')`, `$fs->unmount()` in tearDown — unmounting drops the whole tree.
- One UNIQUE scheme per test class (e.g. 'cachedloadervfs', 'cachewritervfs'; WeavingTransformerTest owns 'vfs') — the suite runs in one process, mounting a taken scheme throws.
- The wrapper supports include/require, mkdir, rename, touch/chmod (stream_metadata), filemtime (url_stat), scandir — but NOT glob(); list directories with scandir().
- Production code needs no special casing: CacheFileWriter's atomic tmp+rename is one universal path that runs identically on vfs and real disk.

## PHPUnit
- Mandatory before commit
- Version: 13+
- If phpstan fails: fix errors before offering to commit

## PHPStan gate
- Mandatory before commit
- `./vendor/bin/phpstan analyze --memory-limit=512M`
- If phpstan fails: fix errors before offering to commit