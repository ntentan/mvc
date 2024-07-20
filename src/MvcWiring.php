<?php
namespace ntentan\mvc;

use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\binders\ViewBinder;
use ntentan\mvc\binders\DefaultModelBinder;

use ntentan\panie\Container;

use ntentan\nibii\ORMContext;
use ntentan\nibii\interfaces\ModelFactoryInterface;
use ntentan\nibii\interfaces\DriverAdapterFactoryInterface;
use ntentan\nibii\interfaces\ValidatorFactoryInterface;
use ntentan\nibii\factories\DefaultModelFactory;
use ntentan\nibii\factories\DriverAdapterFactory;
use ntentan\nibii\factories\DefaultValidatorFactory;
use ntentan\atiaa\DbContext;
use ntentan\atiaa\DriverFactory;


class MvcWiring {
    
    public static function get() {
        return [
            ModelFactoryInterface::class => [DefaultModelFactory::class, 'singleton' => true],
            ValidatorFactoryInterface::class => [DefaultValidatorFactory::class, 'singleton' => true],
            DriverAdapterFactoryInterface::class => [
                function(Container $container) {
                    $config = $container->get('$ntentanConfig:array');
                    return new DriverAdapterFactory($config['db']['driver']);
                }, 
                'singleton' => true
            ],
            ModelBinderRegistry::class => [
                function(Container $container) {
                    
                    $config = $container->get('$ntentanConfig:array');
                    
                    // Register model binders
                    $registry = new ModelBinderRegistry();
                    $registry->setDefaultBinderClass(DefaultModelBinder::class);
                    $registry->register(View::class, ViewBinder::class);
                    
                    if (isset($config['db'])) {
                        ORMContext::initialize(
                            $container->get(ModelFactoryInterface::class),
                            $container->get(DriverAdapterFactoryInterface::class), 
                            $container->get(ValidatorFactoryInterface::class)
                        );
                        DbContext::initialize(new DriverFactory($config['db']));                        
                    }                    
                    return $registry;
                },
                'singleton' => true
            ],
            ServiceContainerBuilder::class => ['singleton' => true]
        ];
    }
}
