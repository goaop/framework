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

namespace Go\Console;

use Closure;
use Go\Console\Command\CacheWarmupCommand;
use Go\Console\Command\DebugAdvisorCommand;
use Go\Console\Command\DebugAspectCommand;
use Go\Console\Command\DebugWeavingCommand;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

/**
 * Lazy loader of the framework's console commands, named after their #[AsCommand] attribute
 */
final class CommandLoader
{
    /**
     * @var list<class-string<Command>>
     */
    public const array COMMANDS = [
        CacheWarmupCommand::class,
        DebugAspectCommand::class,
        DebugAdvisorCommand::class,
        DebugWeavingCommand::class,
    ];

    /**
     * Creates a command loader that instantiates a command only when it is requested
     *
     * @param (Closure(class-string<Command>): Command)|null $instantiate Creates the command, `new $class()` by default
     */
    public static function create(?Closure $instantiate = null): FactoryCommandLoader
    {
        $instantiate ??= static function (string $commandClass): Command {
            /** @var class-string<Command> $commandClass */
            return new $commandClass();
        };

        $factories = [];
        foreach (self::COMMANDS as $commandClass) {
            $attributes = new ReflectionClass($commandClass)->getAttributes(AsCommand::class);
            $name       = $attributes[0]->newInstance()->name;

            $factories[$name] = static fn(): Command => $instantiate($commandClass);
        }

        return new FactoryCommandLoader($factories);
    }
}
