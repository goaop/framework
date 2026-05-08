# tests — Test conventions

## Structure
- Test categories mirror src/ namespaces (e.g. tests/Core/ for src/Core/, tests/Proxy/ for src/Proxy/).
- Functional/integration: tests/Functional/
- Test fixtures (stub classes for weaving): tests/Stubs/, tests/Fixtures/project/src/ (autoloaded as Go\Tests\TestProject\)
- Snapshot fixtures: tests/Instrument/Transformer/_files/ (*-woven.php trait, *-proxy.php proxy)

## PHPUnit
- Mandatory before commit
- Version: 13+
- If phpstan fails: fix errors before offering to commit

## PHPStan gate
- Mandatory before commit
- `./vendor/bin/phpstan analyze --memory-limit=512M`
- If phpstan fails: fix errors before offering to commit