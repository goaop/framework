<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;

/**
 * Replace magic directory and file constants in the source code
 *
 * @package go
 * @subpackage instrument
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
        self::$rootPath = realpath($this->options['appDir']);
        $rewriteToPath  = $this->options['cacheDir'];
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

    /**
     * Replace only magic constants in the code
     *
     * @param StreamMetaData $metadata
     */
    private function replaceMagicConstants(StreamMetaData $metadata)
    {
        $originalUri = $metadata->getResourceUri();
        $replacement = array(
            T_FILE => $originalUri,
            T_DIR  => dirname($originalUri)
        );
        $tokenStream = token_get_all($metadata->source);

        $transformedSource = '';
        foreach ($tokenStream as $token) {
            list ($token, $value) = (array) $token + array(1 => $token);
            if (isset($replacement[$token])) {
                $value = "'" . $replacement[$token] . "'";
            }
            $transformedSource .= $value;
        }
        $metadata->source = $transformedSource;
    }
}
