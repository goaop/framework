<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;

/**
 * Transformer that replaces magic __DIR__ and __FILE__ constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into normalizer method call
 */
class MagicConstantTransformer extends BaseSourceTransformer
{

    /**
     * Root path of application
     *
     * @var string
     */
    protected static $rootPath = '';

    /**
     * Path to rewrite to (cache directory)
     *
     * @var string
     */
    protected static $rewriteToPath = '';

    /**
     * Class constructor
     *
     * @param AspectKernel $kernel Instance of kernel
     */
    public function __construct(AspectKernel $kernel)
    {
        parent::__construct($kernel);
        self::$rootPath      = $this->options['appDir'];
        self::$rewriteToPath = $this->options['cacheDir'];
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void|bool Return false if transformation should be stopped
     */
    public function transform(StreamMetaData $metadata)
    {
        // Make the job only when we use cache directory
        if (!self::$rewriteToPath) {
            return;
        }

        $hasReflectionFilename = strpos($metadata->source, 'getFileName') !== false;
        $hasMagicConstants     = (strpos($metadata->source, '__DIR__') !== false) ||
            (strpos($metadata->source, '__FILE__') !== false);

        if (!$hasMagicConstants && !$hasReflectionFilename) {
            return;
        }

        // Resolve magic constants
        if ($hasMagicConstants) {
            $this->replaceMagicConstants($metadata);
        }

        if ($hasReflectionFilename) {
            // need to make more reliable solution
            $metadata->source = preg_replace(
                '/\$([\w\$\-\>\:\(\)]*?getFileName\(\))/S',
                '\\' . __CLASS__ . '::resolveFileName(\$\1)',
                $metadata->source
            );
        }
    }

    /**
     * Resolves file name from the cache directory to the real application root dir
     *
     * @param string $fileName Absolute file name
     *
     * @return string Resolved file name
     */
    public static function resolveFileName($fileName)
    {
        return str_replace(
            [self::$rewriteToPath, DIRECTORY_SEPARATOR . '_proxies'],
            [self::$rootPath, ''],
            $fileName
        );
    }

    /**
     * Replace only magic constants in the code
     *
     * @param StreamMetaData $metadata
     */
    private function replaceMagicConstants(StreamMetaData $metadata)
    {
        $originalUri = $metadata->uri;
        $replacement = [
            T_FILE => $originalUri,
            T_DIR  => dirname($originalUri)
        ];
        $tokenStream = token_get_all($metadata->source);

        $transformedSource = '';
        foreach ($tokenStream as $token) {
            list ($token, $value) = (array) $token + [1 => $token];
            if (isset($replacement[$token])) {
                $value = "'" . $replacement[$token] . "'";
            }
            $transformedSource .= $value;
        }
        $metadata->source = $transformedSource;
    }
}
