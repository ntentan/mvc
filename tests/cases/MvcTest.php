<?php

namespace ntentan\mvc\tests\cases;

use ntentan\Context;
use ntentan\kaikai\Cache;
use ntentan\mvc\Mvc;
use ntentan\mvc\MvcMiddleware;
use ntentan\mvc\MvcModelFactory;
use ntentan\mvc\Router;
use ntentan\mvc\ServiceContainerBuilder;
use ntentan\nibii\factories\DefaultValidatorFactory;
use ntentan\nibii\factories\DriverAdapterFactory;
use ntentan\nibii\interfaces\DriverAdapterFactoryInterface;
use ntentan\nibii\interfaces\ModelFactoryInterface;
use ntentan\nibii\interfaces\ValidatorFactoryInterface;
use ntentan\nibii\ORMContext;
use ntentan\panie\Container;
use ntentan\sessions\SessionStore;
use PHPUnit\Framework\TestCase;

class MvcTest extends TestCase
{
    public function testConfigureReturnsExpectedWiring(): void
    {
        $wiring = Mvc::configure('my_app', [
            CustomService::class => fn() => new CustomService()
        ]);

        $this->assertArrayHasKey(MvcMiddleware::class, $wiring);
        $this->assertArrayHasKey(ModelFactoryInterface::class, $wiring);
        $this->assertArrayHasKey(ValidatorFactoryInterface::class, $wiring);
        $this->assertArrayHasKey(DriverAdapterFactoryInterface::class, $wiring);
        $this->assertArrayHasKey(ServiceContainerBuilder::class, $wiring);

        $this->assertSame([MvcModelFactory::class, 'singleton' => true], $wiring[ModelFactoryInterface::class]);
        $this->assertSame([DefaultValidatorFactory::class, 'singleton' => true], $wiring[ValidatorFactoryInterface::class]);

        $container = new Container();
        $container->provide('string', 'home')->with(fn() => '/var/app');
        $container->provide('string', 'namespace')->with(fn() => 'my_app');
        $container->provide('array', 'ntentanConfig')->with(fn() => [
            'db' => ['driver' => 'postgresql', 'host' => 'localhost']
        ]);
        $container->setup(array_merge($wiring, [
            Router::class => [Router::class, 'singleton' => true],
            Context::class => fn() => $this->createStub(Context::class),
            SessionStore::class => fn() => $this->createStub(SessionStore::class),
        ]));

        $middleware = $container->get(MvcMiddleware::class);
        $this->assertInstanceOf(MvcMiddleware::class, $middleware);

        $builder = $container->get(ServiceContainerBuilder::class);
        $this->assertInstanceOf(ServiceContainerBuilder::class, $builder);

        $adapterFactory = $container->get(DriverAdapterFactoryInterface::class);
        $this->assertInstanceOf(DriverAdapterFactory::class, $adapterFactory);
    }

    public function testInitializeDatabaseSetsUpContextsWhenDbConfigPresent(): void
    {
        $container = new Container();
        $modelFactory = new MvcModelFactory('my_app');
        $driverAdapterFactory = $this->createStub(DriverAdapterFactoryInterface::class);
        $validatorFactory = $this->createStub(ValidatorFactoryInterface::class);
        $cache = $this->createStub(Cache::class);

        $container->provide('array', 'ntentanConfig')->with(fn() => [
            'db' => ['driver' => 'sqlite', 'file' => ':memory:']
        ]);
        $container->setup([
            ModelFactoryInterface::class => fn() => $modelFactory,
            DriverAdapterFactoryInterface::class => fn() => $driverAdapterFactory,
            ValidatorFactoryInterface::class => fn() => $validatorFactory,
            Cache::class => fn() => $cache,
        ]);

        Mvc::initializeDatabase($container);

        $this->assertSame($modelFactory, ORMContext::getInstance()->getModelFactory());
        $this->assertSame($validatorFactory, ORMContext::getInstance()->getModelValidatorFactory());
        $this->assertSame($cache, ORMContext::getInstance()->getCache());
    }
}

