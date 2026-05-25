<?php
namespace ntentan\mvc;

use ntentan\Context;
use ntentan\honam\EngineRegistry;
use ntentan\honam\engines\php\HelperFactory;
use ntentan\honam\engines\php\HelperVariable;
use ntentan\honam\factories\MustacheEngineFactory;
use ntentan\honam\factories\PhpEngineFactory;
use ntentan\honam\factories\SmartyEngineFactory;
use ntentan\honam\TemplateFileResolver;
use ntentan\honam\TemplateRenderer;
use ntentan\honam\Templates;
use ntentan\panie\Container;
use ntentan\sessions\SessionStore;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ntentan\http\Request;
use ntentan\http\Response;
use ntentan\http\Uri;
use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\binders\DefaultModelBinder;
use ntentan\mvc\binders\ViewBinder;
use Psr\Http\Message\UriInterface;
use ntentan\Configuration;


class ServiceContainerBuilder
{
    private Container $container;
    private SessionStore $session;
    private Context $context;
    private bool $setup;
    private array $bindings = [];
    
    public function __construct(string $home, SessionStore $session, Context $context)
    {
        $this->container = new Container();
        $this->container->provide("string", "home")->with(fn() => $home);
        $this->context = $context;
        $this->session = $session;
        $this->setup = false;
    }

    public function addBindings(array $bindings): void
    {
        $this->bindings = $bindings;
    }

    private function getRequestBindings(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $uri = $request->getUri();
        return [
            Request::class => fn() => $request instanceof Request ? $request : null,
            ServerRequestInterface::class => fn() => $request,
            RequestInterface::class => fn() => $request,
            ResponseInterface::class => fn() => $response,
            Response::class => fn() => $response instanceof Response ? $response : null,
            Uri::class => fn() => $uri instanceof Uri ? $uri : null,
            UriInterface::class => fn() => $uri,
            TemplateRenderer::class => [
                function($container) {
                    /** @var EngineRegistry $engineRegistry */
                    $engineRegistry = $container->get(EngineRegistry::class);
                    $templateFileResolver = $container->get(TemplateFileResolver::class);
                    $templateRenderer = new TemplateRenderer($engineRegistry, $templateFileResolver);
                    $engineRegistry->registerEngine(['mustache'], $container->get(MustacheEngineFactory::class));
                    $engineRegistry->registerEngine(['smarty', 'tpl'], $container->get(SmartyEngineFactory::class));
                    $helperVariable = new HelperVariable($this->container->get(HelperFactory::class), $templateRenderer);
                    $engineRegistry->registerEngine(['tpl.php'], new PhpEngineFactory($templateRenderer, $helperVariable));
                    return $templateRenderer;
                },
                'singleton' => true
            ]
        ];
    }

    private function getBindings(): array
    {
        return [
            Context::class => [fn() => $this->context, 'singleton' => true],
            Templates::class => [Templates::class, 'singleton' => true],
            ModelBinderRegistry::class => [
                function(Container $container) {
                    // Register model binders
                    $registry = new ModelBinderRegistry();
                    $registry->setDefaultBinderClass(DefaultModelBinder::class);
                    $registry->register(View::class, ViewBinder::class);
                    return $registry;
                },
                'singleton' => true
            ],
            TemplateFileResolver::class => [
                function(Container $container) {
                    $fileResolver = new TemplateFileResolver();
                    $home = $container->get('$home:string');
                    $fileResolver->appendToPathHierarchy("$home/views/shared");
                    $fileResolver->appendToPathHierarchy("$home/views/layouts");
                    return $fileResolver;
                },
                'singleton' => true
            ],
            HelperFactory::class => [
                function($container) {
                    /** @var UriInterface $uri */
                    $uri = $container->get(UriInterface::class);
                    /** @var Context $context */
                    $context = $container->get(Context::class);
                    return new HelperFactory($uri->getPath(), $context->getPrefix());
                },
                'singleton' => true
            ],
            SessionStore::class => [fn() => $this->session, 'singleton' => true]
        ];
    }

    public function setRequestDetails(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->container->setup($this->getRequestBindings($request, $response));
    }

    public function getContainer(): Container
    {
        if (!$this->setup) {
            $this->container->setup($this->getBindings());
            $this->container->setup($this->bindings);
            $this->setup = true;
        }
        return $this->container;
    }
}
