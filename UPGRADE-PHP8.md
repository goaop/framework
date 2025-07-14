# PHP 8 Compatibility Guide

This document outlines the PHP 8 compatibility status of the Go! AOP Framework and provides guidance for users upgrading to PHP 8+.

## Current Status

The Go! AOP Framework has been updated to support PHP 8.2+ and includes comprehensive support for PHP 8 language features:

- ✅ **PHP 8.0 Features**: Union types, named parameters, attributes, constructor property promotion, mixed type, static return type
- ✅ **PHP 8.1 Features**: Readonly properties, enums, intersection types, never return type, final class constants
- ✅ **PHP 8.2 Features**: Readonly classes, DNF types, null/false/true types, constants in traits
- ✅ **Basic Framework Functionality**: All core framework components work correctly with PHP 8+

## Known Issues

### getConstants() Method Filter Parameter

The `goaop/parser-reflection` dependency includes a compatibility implementation of `ReflectionClass::getConstants(?int $filter = null)` that accepts the PHP 8 filter parameter but **does not properly implement the filtering logic**.

**Impact**: Code that relies on filtering constants by visibility (public, protected, private) will receive all constants instead of filtered results.

**Example**:
```php
// This will return ALL constants instead of just public ones
$constants = $parserReflectionClass->getConstants(ReflectionClassConstant::IS_PUBLIC);
```

**Workaround**: If you need filtered constants, use the native PHP ReflectionClass when possible:
```php
// Use native reflection when the class is already loaded
$nativeReflection = new \ReflectionClass($className);
$publicConstants = $nativeReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
```

## Requirements

- **PHP Version**: 8.2 or higher
- **Dependencies**: 
  - `goaop/parser-reflection`: 4.x-dev (PHP 8 compatible)
  - `nikic/php-parser`: ^5.0
  - Other dependencies are automatically resolved

## Migration from Older Versions

If you're upgrading from an older version of the framework that used `goaop/parser-reflection` 2.x:

1. **Update your composer.json**:
   ```json
   {
     "require": {
       "goaop/framework": "^3.0",
       "php": "^8.2"
     }
   }
   ```

2. **Run composer update**:
   ```bash
   composer update
   ```

3. **Test your application** with the updated dependencies.

## Reporting Issues

If you encounter PHP 8 compatibility issues:

1. Verify you're using the latest version of the framework
2. Check that all dependencies are up to date
3. Review this guide for known issues
4. Report new issues on the [GitHub repository](https://github.com/goaop/framework/issues)

## Contributing

Help improve PHP 8 compatibility by:

- Testing the framework with your PHP 8+ applications
- Reporting compatibility issues
- Contributing test cases for new PHP features
- Submitting pull requests for fixes

---

*Last updated: 2025-07-09*