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

namespace Go\Bridge\Doctrine;

use Go\Bridge\Doctrine\Fixtures\Excluded\ExcludedEntity;
use Go\Bridge\Doctrine\Fixtures\LocatedEntity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WovenEntityClassLocatorTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/Fixtures';

    public function testLocatesOnlyAutoloadableClassesWithoutIncludingFiles(): void
    {
        $locator = WovenEntityClassLocator::createFromDirectories([self::FIXTURES]);

        $classNames = $locator->getClassNames();

        sort($classNames);
        // Interfaces, traits and enums are skipped, the excluded directory is not excluded here
        $this->assertSame([ExcludedEntity::class, LocatedEntity::class], $classNames);
        // A class the autoloader can not load is skipped: its file must never be included, as a
        // direct include would bypass the weaver (the woven version is served by the autoloader)
        $this->assertFalse(class_exists('Go\Bridge\Doctrine\Fixtures\NotAutoloadableEntity', false));
    }

    public function testSkipsExcludedDirectories(): void
    {
        $relative = WovenEntityClassLocator::createFromDirectories([self::FIXTURES], ['Excluded']);
        $absolute = WovenEntityClassLocator::createFromDirectories([self::FIXTURES], [self::FIXTURES . '/Excluded']);

        $this->assertSame([LocatedEntity::class], $relative->getClassNames());
        $this->assertSame([LocatedEntity::class], $absolute->getClassNames());
    }

    public function testAcceptsPlainFileList(): void
    {
        $locator = new WovenEntityClassLocator([self::FIXTURES . '/LocatedEntity.php', self::FIXTURES . '/missing.php']);

        $this->assertSame([LocatedEntity::class], $locator->getClassNames());
    }

    public function testRejectsMissingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        WovenEntityClassLocator::createFromDirectories([self::FIXTURES . '/missing']);
    }
}
