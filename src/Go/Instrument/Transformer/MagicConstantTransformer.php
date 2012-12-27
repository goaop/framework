<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

/**
 * Replace magic directory and file constants in the source code
 *
 * @package go
 * @subpackage instrument
 */
class MagicConstantTransformer implements SourceTransformer
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
     * @param array $options Configuration options from kernel
     */
    public function __construct(array $options)
    {
        self::$rootPath = realpath($options['appDir']);
        $rewriteToPath  = $options['cacheDir'];
        if ($rewriteToPath) {
            self::$rewriteToPath = realpath($rewriteToPath);
        }
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void
     */
    public function transform(StreamMetaData $metadata)
    {
        // Make the job only when we use cache directory
        if (!self::$rewriteToPath) {
            return;
        }

        $hasReflecitionFilename = strpos($metadata->source, 'getFileName') !== false;
        $notHasMagicConsts      = (strpos($metadata->source, '__DIR__') === false) && (strpos($metadata->source, '__FILE__') === false);

        if ($notHasMagicConsts && !$hasReflecitionFilename) {
            return;
        }

        // Resolve magic constanst
        if (!$notHasMagicConsts) {
            $originalUri = $metadata->getResourceUri();

            $metadata->source = strtr(
                $metadata->source,
                array(
                    '__DIR__'  => "'" . dirname($originalUri) . "'",
                    '__FILE__' => "'" . $originalUri . "'",
                )
            );
        }

        if ($hasReflecitionFilename) {
            // TODO: need to make more reliable solution
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
        return str_replace(self::$rewriteToPath, self::$rootPath, $fileName);
    }
}
