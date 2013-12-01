<?php

namespace Go\Instrument;

use Doctrine\Common\Annotations\AnnotationException;
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
            try {
                FilterInjectorTransformer::rewrite($file);
            } catch(AnnotationException $e) {

            }
        }
    }
}