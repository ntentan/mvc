<?php
namespace ntentan\mvc;

use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\binders\ViewBinder;
use ntentan\controllers\model_binders\DefaultModelBinder;
use ntentan\panie\Container;

/**
 */
class MvcWiring {
    
    public static function get() {
        return [
            ModelBinderRegistry::class => [
                function(Container $container) {
                    $registry = new ModelBinderRegistry();
                    $registry->setDefaultBinderClass(DefaultModelBinder::class);
                    $registry->register(View::class, ViewBinder::class);
                    return $registry;
                },
                'singleton' => true
            ],
            ServiceContainerBuilder::class => ['singleton' => true]
        ];
    }
}
