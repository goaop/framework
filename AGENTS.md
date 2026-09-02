# Go! AOP Framework
goaop/framework | NS: Go\ | PHP: ^8.4.0
AOP via source transformation at load time (stream filter, no PECL, no eval).

## Agent gate
- PHP 8.4+ required. If PHP 8.3 or less → STOP, report can't run tests/phpstan.
- Gate: `composer analyze` before commit (phpstan level 10).

## Commands
| Action    | Command                                                               |
|-----------|-----------------------------------------------------------------------|
| install   | `composer install`                                                    |
| test:all  | `composer test` (= `./vendor/bin/phpunit`)                            |
| test:file | `./vendor/bin/phpunit tests/Core/ContainerTest.php`                   |
| test:one  | `./vendor/bin/phpunit --filter testName tests/Core/ContainerTest.php` |
| analyze   | `composer analyze` (= `./vendor/bin/phpstan analyze --memory-limit=512M`) |
| cs        | `composer cs` (= `./vendor/bin/php-cs-fixer check --diff`, PER-CS)    |
| cs:fix    | `composer cs:fix` (= `./vendor/bin/php-cs-fixer fix`)                 |
| check     | `composer check` (cs + analyze + test)                                |

## Architecture overview
Intercepts PHP class loading pipeline: source stream filter transforms source → injects interception hooks → caches result.
- Init: AspectKernel::init() → stream filter → transformers → configureAop()
- Main transformer: WeavingTransformer (class→trait, proxy class re-inherits parent+interfaces)
- Proxy dispatch: per-method static $__joinPoint → InterceptorInjector → interceptor chain of first-class advice callables (The::aspect(X::class)->m(...), The::advice('id') for closure advices)

## Directory → AGENTS.md map
| Directory         | Sub-AGENTS.md              | Covers                                                        |
|-------------------|----------------------------|---------------------------------------------------------------|
| `src/Instrument/` | `src/Instrument/AGENTS.md` | Init flow, transformers, trait engine, line numbers, Override |
| `src/Proxy/`      | `src/Proxy/AGENTS.md`      | Proxy generators, code-gen, readonly, hooks, enums            |
| `src/Aop/`        | `src/Aop/AGENTS.md`        | Interfaces, generics, implementations, pointcuts, attributes  |
| `src/Core/`       | `src/Core/AGENTS.md`       | Container, aspect loading, advice matching, bridge            |
| `tests/`          | `tests/AGENTS.md`          | Test conventions, fixtures, PHPUnit, PHPStan                  |

## Rules
- Skip explanations unless asked. Show changes, not commentary.
- Use targeted edits (Edit tool) over full-file rewrites.
- No filler words ("let me", "carefully", "I'll now").
- Before commit: phpunit and phpstan must pass. Fix errors before offering to commit.
