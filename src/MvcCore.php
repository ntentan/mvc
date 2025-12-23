<?php
namespace ntentan\mvc;

use ntentan\Context;
use ntentan\panie\Container;
use ntentan\nibii\ORMContext;
use ntentan\nibii\interfaces\ModelFactoryInterface;
use ntentan\nibii\interfaces\DriverAdapterFactoryInterface;
use ntentan\nibii\interfaces\ValidatorFactoryInterface;
use ntentan\nibii\factories\DriverAdapterFactory;
use ntentan\nibii\factories\DefaultValidatorFactory;
use ntentan\atiaa\DbContext;
use ntentan\atiaa\DriverFactory;
use ntentan\kaikai\Cache;
use ntentan\sessions\SessionStore;


class MvcCore {
    
    /**
     * Initialize database subsystem of the MVC middleware.
     * This is useful in cases where the Model infrastructure is needed outside of the MVC system. The 
     * `configureAndGetWiring` method must be called to setup all parameters before this method is called. 
     */
    public static function initializeDatabase(Container $container): void
    {
        $configuration = $container->get('$ntentanConfig:array');
        if (isset($configuration['db'])) {
            ORMContext::initialize(
                    $container->get(ModelFactoryInterface::class),
                    $container->get(DriverAdapterFactoryInterface::class),
                    $container->get(ValidatorFactoryInterface::class),
                    $container->get(Cache::class)
                );
            DbContext::initialize(new DriverFactory($configuration['db']));
        } 
    }
    
    /**
     * Get the MVC middleware's container configuration. 
     * Passing the output of
     * 
     * @return array
     */
    public static function configure(string $namespace, array $bindings = []): array
    {
        return [
            MvcMiddleware::class => [
                function(Container $container) use ($namespace) {
                    $instance = new MvcMiddleware(
                        $container->get(Router::class), 
                        $container->get(ServiceContainerBuilder::class), 
                        $container->get(Context::class)
                    );
                    $instance->setNamespace($namespace);
                    self::initializeDatabase($container);
                    return $instance;
                },
                'singleton' => true
            ],
            
            ModelFactoryInterface::class => [MvcModelFactory::class, 'singleton' => true],
            
            ValidatorFactoryInterface::class => [DefaultValidatorFactory::class, 'singleton' => true],
            
            DriverAdapterFactoryInterface::class => [
                function(Container $container) {
                    $config = $container->get('$ntentanConfig:array');
                    return new DriverAdapterFactory($config['db']['driver']);
                }, 
                'singleton' => true
            ],
            
            ServiceContainerBuilder::class => [
                function($container) use ($bindings) {
                    $home = $container->get("\$home:string");
                    $sessionStore = $container->get(SessionStore::class);
                    $context = $container->get(Context::class);
                    $containerBuilder = new ServiceContainerBuilder($home, $sessionStore, $context);
                    $containerBuilder->addBindings($bindings);
                    return $containerBuilder;
                },
                'singleton' => true
            ]
        ];
    }
}
