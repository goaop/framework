# tests — Test conventions

## Structure
- Test categories mirror src/ namespaces (e.g. tests/Core/ for src/Core/, tests/Proxy/ for src/Proxy/).
- Functional/integration: tests/Functional/
- General test stubs (weaving-input classes, plus generator reflection targets under tests/Stubs/Generator/ as Go\Stubs\Generator): tests/Stubs/
- Transformer-test stubs (version-specific weaving inputs): tests/Instrument/Transformer/Stubs/
- Fixture project (autoloaded as Go\Tests\TestProject\): tests/Fixtures/project/src/
- Snapshot fixtures: tests/Instrument/Transformer/_files/ (*-woven.php trait, *-proxy.php proxy)

## PHPUnit
- Mandatory before commit
- Version: 13+
- If phpstan fails: fix errors before offering to commit

## PHPStan gate
- Mandatory before commit
- `./vendor/bin/phpstan analyze --memory-limit=512M`
- If phpstan fails: fix errors before offering to commit