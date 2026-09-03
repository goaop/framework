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

namespace Go\Core;

use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;

/**
 * Deferred definitions of the framework's own services, registered by the kernel during
 * initialization through the generic lazy container API.
 *
 * Lives outside the container on purpose: the container is a generic DI implementation
 * with no knowledge of the AOP services it happens to hold.
 */
final class FrameworkServices
{
    public static function register(AspectContainer $container): void
    {
        $container->addLazyService(PointcutLexer::class, fn(): PointcutLexer => new PointcutLexer());

        $container->addLazyService(PointcutParser::class, fn(): PointcutParser => new PointcutParser(
            new PointcutGrammar(),
        ));

        $container->addLazyService(AdviceMatcher::class, fn(AspectContainer $container): AdviceMatcher => new AdviceMatcher(
            (bool) $container->getValue('kernel.interceptFunctions'),
        ));

        $container->addLazyService(AttributeAspectLoaderExtension::class, fn(AspectContainer $container): AttributeAspectLoaderExtension => new AttributeAspectLoaderExtension(
            $container->getService(PointcutLexer::class),
            $container->getService(PointcutParser::class),
        ));

        $container->addLazyService(IntroductionAspectExtension::class, fn(AspectContainer $container): IntroductionAspectExtension => new IntroductionAspectExtension(
            $container->getService(PointcutLexer::class),
            $container->getService(PointcutParser::class),
        ));

        $container->addLazyService(AspectLoader::class, fn(AspectContainer $container): AspectLoader => new AspectLoader(
            $container,
            $container->getService(AttributeAspectLoaderExtension::class),
            $container->getService(IntroductionAspectExtension::class),
        ));

        $container->addLazyService(CachedAspectLoader::class, function (AspectContainer $container): CachedAspectLoader {
            $options = $container->getService(AspectKernel::class)->getOptions();

            return new CachedAspectLoader($container, AspectLoader::class, $options);
        });

        $container->addLazyService(CachePathManager::class, fn(AspectContainer $container): CachePathManager => new CachePathManager(
            $container->getService(AspectKernel::class),
        ));
    }
}
