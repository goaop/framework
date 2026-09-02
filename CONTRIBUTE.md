# How to contribute

## Installation

Before you contribute make sure you install all necessary dependencies using:

```bash
$ composer install
```

## Run Tests

To run tests use the composer script (a shortcut for the `phpunit` executable in `vendor/bin`):

```bash
$ composer test
# or directly:
$ ./vendor/bin/phpunit
```

You should get an output similar to this

```bash
$ composer test

PHPUnit 4.8.29 by Sebastian Bergmann and contributors.
 
..........................SSSSSS...............................  63 / 157 ( 40%)
............................................................... 126 / 157 ( 80%)
...............................
 
Time: 658 ms, Memory: 25.00MB
 
OK, but incomplete, skipped, or risky tests!
Tests: 157, Assertions: 207, Skipped: 6.
```

## Static Analysis

To run PHPStan static analysis (level 10):

```bash
$ composer analyze
# or directly:
$ ./vendor/bin/phpstan analyze --memory-limit=512M
```

## Coding Standards

The code base follows [PER-CS](https://www.php-fig.org/per/coding-style/) (plus `declare(strict_types=1)` in every file), enforced by php-cs-fixer. To check for violations:

```bash
$ composer cs
# or directly:
$ ./vendor/bin/php-cs-fixer check --diff
```

To fix violations automatically:

```bash
$ composer cs:fix
```

## Full Check

To run the coding-standards check, static analysis and the test suite in one go:

```bash
$ composer check
```
