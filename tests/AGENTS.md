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
## Remote sessions (Claude Code on the web) — installing phpstan
Diagnosed 2026-09-03; hits every fresh remote session of this repo.

### `composer install` is slow (everything from source) and dies on phpstan/phpstan
What blocks dist installs:
- The session's GitHub proxy serves `api.github.com` / `codeload.github.com` / `github.com/*/archive` only
  for repos attached to the session (this one). Every other package's zipball answers
  `403 "GitHub access to this repository is not enabled for this session"` — with or without a token,
  so no `COMPOSER_AUTH`/`github-oauth` setting and no composer option can fix it. `add_repo` does not
  open it either (read = git only; API needs an attached repo with credentials, refused cross-owner).
- Composer then falls back to `source` per package (full `git clone` into ~/.cache/composer/vcs):
  anonymous git reads of public repos DO pass the proxy, so it works, but ~80 failed dist attempts
  (some as "Proxy CONNECT aborted due to timeout") plus 80 clones take 70–85 s instead of ~30 s.
- phpstan/phpstan has no `source` at all: packagist metadata and composer.lock say `"source": null`
  (its composer.json declares an empty `source` block on purpose; the code lives in phpstan/phpstan-src,
  the package is just the phar). Nothing to fall back to → the whole install aborts with
  "Could not authenticate against github.com".

Fix A (recommended, dist for everything, ~30 s): point composer at a packagist mirror that hosts its own
dist zips. Non-GitHub hosts are not gated by the proxy. Verified: mirrors.cloud.tencent.com rewrites
every dist URL to its own host, is up to date with packagist (same-day releases), and its zips are
byte-identical to the GitHub tags (checked phpstan.phar sha256 against the git tag and the release
asset). Not usable: mirrors.aliyun.com (declares dist mirrors but serves 404), mirrors.huaweicloud.com
(rewrites URLs, serves 404), packagist.jp (metadata only, dists stay on GitHub).

```bash
# global (session-local) composer config, composer.json stays untouched; composer.lock is gitignored,
# so remove a stale lock (it would pin api.github.com dist URLs) and let install resolve via the mirror
COMPOSER_ALLOW_SUPERUSER=1 composer config -g repos.packagist composer https://mirrors.cloud.tencent.com/composer
rm -f composer.lock
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction     # 83 × "Extracting archive", 0 fallbacks
./vendor/bin/phpstan --version                                   # → PHPStan 2.x
```

Fix B (fallback when the mirror is unreachable): seed composer's dist cache for phpstan/phpstan with a
zip built from a shallow git clone of the release tag. Composer looks up
`<cache-files-dir>/phpstan/phpstan/<sha1(dist url)>.zip` and, since the lock carries no shasum for it,
uses the file as-is. `--prefer-source` makes the other packages skip the doomed dist attempts.

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
