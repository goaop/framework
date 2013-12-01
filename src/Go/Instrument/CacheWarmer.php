<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Martin
 * Date: 13-12-01
 * Time: 11:30
 * To change this template use File | Settings | File Templates.
 */

namespace Go\Instrument;


use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\Transformer\FilterInjectorTransformer;

class CacheWarmer
{
    public function warmUp()
    {
        foreach(spl_autoload_functions() as $autoLoader) {
            if(!is_array($autoLoader)) {
                continue;
            }

            if(!is_object($autoLoader[0])) {
                continue;
            }

            if(!($autoLoader[0] instanceof AopComposerLoader)) {
                continue;
            }

            $this->warmUpFiles(array_filter($autoLoader[0]->getOriginalLoader()->getClassMap()));

        }
    }

    public function warmUpFiles($files)
    {
        foreach($files as $file) {
            FilterInjectorTransformer::rewrite($file);
        }
    }
}