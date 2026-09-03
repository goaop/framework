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
## Remote sessions (Claude Code on the web) — install and gate pitfalls
Diagnosed 2026-09-03; both symptoms hit every fresh remote session of this repo.

### `composer install` dies with "Could not authenticate against github.com" on phpstan/phpstan
- Cause 1: the session's egress proxy serves GitHub HTTPS only for repos attached to the session
  (this repo). Every other `api.github.com/repos/<vendor>/<pkg>/zipball/...` (and codeload / archive URLs)
  answers `403 "GitHub access to this repository is not enabled for this session"` — also with a token.
  Composer then falls back to `source` (a `git clone`), and anonymous git reads of public repos DO pass
  the proxy, so every other package installs (slowly: ~80 failed dist attempts, some as "Proxy CONNECT
  aborted due to timeout").
- Cause 2: phpstan/phpstan ships dist only. Its packagist metadata and composer.lock entry have
  `"source": null` (the repo's composer.json even declares an empty `source` block on purpose; the
  code lives in phpstan/phpstan-src, the package is just the phar). No fallback → the whole install aborts.
- Neither `add_repo` (read = git only, API stays closed) nor `COMPOSER_AUTH`/`GITHUB_TOKEN` helps.
- Fix: seed composer's dist cache with a zip built from a shallow git clone of the release tag.
  Composer looks up `<cache-files-dir>/phpstan/phpstan/<sha1(dist url)>.zip` and, since the lock carries
  no shasum for it, uses the file as-is. Then install with `--prefer-source` so the other packages skip
  the doomed dist attempts.

```bash
# composer.lock is gitignored: resolve it first, without downloading (packagist metadata is reachable)
[ -f composer.lock ] || COMPOSER_ALLOW_SUPERUSER=1 composer update --no-install --no-interaction
# version, dist reference and cache key of the locked phpstan/phpstan
eval "$(php -r '$l = json_decode(file_get_contents("composer.lock"), true);
  foreach (array_merge($l["packages"], $l["packages-dev"]) as $p) {
      if ($p["name"] === "phpstan/phpstan") {
          printf("V=%s REF=%s KEY=%s\n", $p["version"], $p["dist"]["reference"], sha1($p["dist"]["url"]));
      }
  }')"
# shallow-clone the release tag (anonymous git reads of public repos pass the proxy, ~5s)
TMP=$(mktemp -d); git clone -q --depth 1 --branch "$V" https://github.com/phpstan/phpstan.git "$TMP/phpstan"
# zip it into composer's dist cache under the key composer will look for (.gitattributes export-ignore applies)
CACHE="$(composer config --global cache-files-dir 2>/dev/null)/phpstan/phpstan"; mkdir -p "$CACHE"
git -C "$TMP/phpstan" archive --format=zip --prefix="phpstan-phpstan-${REF:0:7}/" -o "$CACHE/$KEY.zip" HEAD
# phpstan comes from the cache, everything else from git; ALLOW_SUPERUSER keeps phpstan/extension-installer active
COMPOSER_ALLOW_SUPERUSER=1 composer install --prefer-source --no-interaction
./vendor/bin/phpstan --version   # → PHPStan 2.x
```

### 32 Functional failures under PHP 8.5 ("Unexpected token Go ... Expected one of: ..., namePart")
- The environment's `/etc/php/8.5/cli/conf.d/99-agent.ini` turns on `opcache.enable_cli=1` +
  `opcache.jit=tracing`. Under PHP 8.5.10 the tracing JIT miscompiles the LALR parser loop in
  goaop/dissect (`isset($table[$state][$type])` misses a key that is present), so every pointcut of the
  fixture project fails to parse in the spawned `bin/console cache:warmup:aop` process. Same code,
  same input: fine with JIT off, fine on PHP 8.4, fine on CI (setup-php leaves CLI opcache off).
- `php -d opcache.jit=0 vendor/bin/phpunit` is NOT enough: BaseFunctionalTestCase spawns plain
  `php` subprocesses. Append an ini dir instead (the leading `:` keeps the default scan dir):

```bash
mkdir -p /tmp/php-ini && printf 'opcache.jit=0\n' > /tmp/php-ini/zz-nojit.ini
PHP_INI_SCAN_DIR=":/tmp/php-ini" ./vendor/bin/phpunit      # → OK (2666 tests)
```
