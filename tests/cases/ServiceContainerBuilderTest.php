<?php

namespace ntentan\mvc\tests\cases;

use ntentan\Context;
use ntentan\honam\engines\php\HelperFactory;
use ntentan\honam\TemplateFileResolver;
use ntentan\honam\TemplateRenderer;
use ntentan\honam\Templates;
use ntentan\http\Request;
use ntentan\http\Response;
use ntentan\http\Uri;
use ntentan\mvc\binders\DefaultModelBinder;
use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\binders\ViewBinder;
use ntentan\mvc\ServiceContainerBuilder;
use ntentan\mvc\View;
use ntentan\sessions\SessionStore;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class CustomService
{
    public string $tag = 'custom';
}

class ServiceContainerBuilderTest extends TestCase
{
    public function testGetContainerResolvesBuiltInServices(): void
    {
        $session = $this->createStub(SessionStore::class);
        $context = $this->createStub(Context::class);
        $context->method('getPrefix')->willReturn('/app');

        $uri = new Uri('https://example.com/users/list');
        $request = $this->createStub(Request::class);
        $request->method('getUri')->willReturn($uri);
        $response = new Response();

        $builder = new ServiceContainerBuilder('/srv/app', $session, $context);
        $container = $builder->getContainer($request, $response);

        $this->assertSame('/srv/app', $container->get('$home:string'));
        $this->assertSame($context, $container->get(Context::class));
        $this->assertSame($session, $container->get(SessionStore::class));
        $this->assertSame($request, $container->get(Request::class));
        $this->assertSame($request, $container->get(ServerRequestInterface::class));
        $this->assertSame($request, $container->get(RequestInterface::class));
        $this->assertSame($response, $container->get(Response::class));
        $this->assertSame($response, $container->get(ResponseInterface::class));
        $this->assertSame($uri, $container->get(Uri::class));
        $this->assertSame($uri, $container->get(UriInterface::class));

        /** @var ModelBinderRegistry $registry */
        $registry = $container->get(ModelBinderRegistry::class);
        $this->assertInstanceOf(ModelBinderRegistry::class, $registry);
        $this->assertSame(DefaultModelBinder::class, $registry->getDefaultBinderClass());
        $this->assertSame(ViewBinder::class, $registry->get(View::class));

        $this->assertInstanceOf(TemplateFileResolver::class, $container->get(TemplateFileResolver::class));
        $this->assertInstanceOf(HelperFactory::class, $container->get(HelperFactory::class));
        $this->assertInstanceOf(TemplateRenderer::class, $container->get(TemplateRenderer::class));
        $this->assertInstanceOf(Templates::class, $container->get(Templates::class));
    }

    public function testAddBindingsRegistersCustomServices(): void
    {
        $session = $this->createStub(SessionStore::class);
        $context = $this->createStub(Context::class);

        $uri = $this->createStub(UriInterface::class);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $response = $this->createStub(ResponseInterface::class);

        $customInstance = new CustomService();
        $customInstance->tag = 'injected';

        $builder = new ServiceContainerBuilder('/srv/app', $session, $context);
        $builder->addBindings([
            CustomService::class => fn() => $customInstance
        ]);

        $container = $builder->getContainer($request, $response);

        $this->assertSame($customInstance, $container->get(CustomService::class));

        $this->expectException(\ntentan\panie\exceptions\ResolutionException::class);
        $container->get(Request::class);
    }
}
