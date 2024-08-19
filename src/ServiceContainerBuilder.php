<?php
namespace ntentan\mvc;

use ntentan\honam\EngineRegistry;
use ntentan\honam\engines\php\HelperVariable;
use ntentan\honam\engines\php\Janitor;
use ntentan\honam\factories\MustacheEngineFactory;
use ntentan\honam\factories\PhpEngineFactory;
use ntentan\honam\factories\SmartyEngineFactory;
use ntentan\honam\TemplateFileResolver;
use ntentan\honam\TemplateRenderer;
use ntentan\honam\Templates;
use ntentan\panie\Container;
use ntentan\sessions\SessionStore;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ntentan\http\Request;
use ntentan\http\Response;
use ntentan\http\Uri;
use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\binders\DefaultModelBinder;
use ntentan\mvc\binders\ViewBinder;


class ServiceContainerBuilder
{
    private Container $container;
    private SessionStore $session;
    
    public function __construct(string $home, SessionStore $session)
    {
        $this->container = new Container();
        $this->container->provide("string", "home")->with(fn() => $home);
        $this->session = $session;
    }

    public function getContainer(ServerRequestInterface $request, ResponseInterface $response)
    {
        $uri = $request->getUri();
        $this->container->setup([
            Templates::class => [Templates::class, 'singleton' => true],
            Request::class => fn() => $request instanceof Request ? $request : null,
            Response::class => fn() => $response instanceof Response ? $response : null,
            Uri::class => fn() => $uri instanceof Uri ? $uri : null,
            ServerRequestInterface::class => fn() => $request,
            ResponseInterface::class => fn() => $response,
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
            TemplateRenderer::class => [
                    function($container) {
                        /** @var EngineRegistry $engineRegistry */
                        $engineRegistry = $container->get(EngineRegistry::class);
                        $templateFileResolver = $container->get(TemplateFileResolver::class);
                        $templateRenderer = new TemplateRenderer($engineRegistry, $templateFileResolver);
                        $engineRegistry->registerEngine(['mustache'], $container->get(MustacheEngineFactory::class));
                        $engineRegistry->registerEngine(['smarty', 'tpl'], $container->get(SmartyEngineFactory::class));
                        $engineRegistry->registerEngine(['tpl.php'],
                            new PhpEngineFactory($templateRenderer,
                                new HelperVariable($templateRenderer, $container->get(TemplateFileResolver::class)),
                                $container->get(Janitor::class)
                            ));
                        return $templateRenderer;
                    },
                    'singleton' => true
                    ],
            SessionStore::class => [fn() => $this->session, 'singleton' => true]
        ]);
        return $this->container;
    }
}