# Contributing

Thank you for helping to improve Go! AOP. Bug reports, documentation fixes and pull requests are welcome.
By participating you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md). Report security issues privately,
see [SECURITY.md](SECURITY.md).

## Requirements

- PHP 8.4 or newer with `ext-tokenizer`
- Composer

## Setup

```bash
git clone https://github.com/goaop/framework.git
cd framework
composer install
```

## Checks

Run the same checks as CI before you open a pull request:

| Command                     | What it does                                                            |
|-----------------------------|-------------------------------------------------------------------------|
| `composer test`             | PHPUnit test suite                                                      |
| `composer analyze`          | PHPStan, level 10                                                       |
| `composer cs`               | Coding standards check ([PER-CS](https://www.php-fig.org/per/coding-style/) and `declare(strict_types=1)`) |
| `composer cs:fix`           | Fix coding standards violations                                         |
| `composer check`            | All of the above checks in one go                                       |
| `composer test:performance` | Joinpoint hot-path performance group (run it when you change `src/Aop/Framework`) |

A single test file or test:

```bash
./vendor/bin/phpunit tests/Core/ContainerTest.php
./vendor/bin/phpunit --filter testName tests/Core/ContainerTest.php
```

## Pull requests

- Use [Conventional Commits](https://www.conventionalcommits.org/) for commit messages and pull request titles,
  e.g. `fix(proxy): keep short imports in generated proxies`.
- Add tests for every bug fix and feature.
- Mention user-facing and breaking changes in [CHANGELOG.md](CHANGELOG.md); breaking changes also need an entry in
  the upgrade guide.
- Keep generated code (proxies, woven traits, advisor caches) clean and readable.
