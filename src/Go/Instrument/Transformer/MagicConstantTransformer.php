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
        self::$rootPath      = realpath($options['appDir']);
        self::$rewriteToPath = realpath($options['cacheDir']);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param string $source Source for class
     * @param StreamMetaData $metadata Metadata for source
     *
     * @return string Transformed source
     */
    public function transform($source, StreamMetaData $metadata = null)
    {
        // Make the job only when we use cache directory
        if (!self::$rewriteToPath) {
            return $source;
        }

        $hasReflecitionFilename = strpos($source, 'getFileName') !== false;
        $notHasMagicConsts      = (strpos($source, '__DIR__') === false) && (strpos($source, '__FILE__') === false);

        if ($notHasMagicConsts && !$hasReflecitionFilename) {
            return $source;
        }

        // Resolve magic constanst
        if (!$notHasMagicConsts) {
            $originalUri = $metadata->getResourceUri();

            $source = strtr(
                $source,
                array(
                    '__DIR__'  => "'" . dirname($originalUri) . "'",
                    '__FILE__' => "'" . $originalUri . "'",
                )
            );
        }

        if ($hasReflecitionFilename) {
            // TODO: need to make more reliable solution
            $source = preg_replace(
                '/\$([\w\$\-\>\:\(\)]*?getFileName\(\))/',
                '\\' . __CLASS__ . '::resolveFileName(\$\1)',
                $source
            );
        }

        return $source;
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
