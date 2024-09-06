<?php

namespace ntentan\mvc;

use ntentan\Context;
use ntentan\mvc\binders\ModelBinderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ntentan\Middleware;
use ntentan\panie\Container;
use ntentan\utils\Text;
use ntentan\exceptions\NtentanException;
use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\http\filters\Header;
use ntentan\http\filters\Method;
use ntentan\http\filters\MimeHeader;
use ntentan\http\StringStream;

/**
 * Responds to requests by initializing classes according to an MVC pattern.
 */
class MvcMiddleware implements Middleware
{
    private const FILTER_ATTRIBUTES = [Header::class, Method::class, MimeHeader::class];
    
    private Router $router;
    
    private ServiceContainerBuilder $containerBuilder;
    
    private ModelBinderRegistry $modelBinders;
    
    private Container $serviceContainer;

    private string $namespace = 'app';

    private Context $context;

    public function __construct(Router $router, ServiceContainerBuilder $containerBuilder, Context $context)
    {
        $this->router = $router;
        $this->containerBuilder = $containerBuilder;
        $this->context = $context;
    }
    
    protected function getServiceContainer(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(!isset($this->serviceContainer)) {
            $this->serviceContainer = $this->containerBuilder->getContainer($request, $response);
        }
        return $this->serviceContainer;
    }

    protected function getContext(): Context
    {
        return $this->context;
    }
    
    /**
     * Returns a structural array with information about the controller class to load, the action method to call, and
     * the parameters to bind to the action method.
     * 
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function getControllerSpec(ServerRequestInterface $request): ControllerSpec
    {
        $uri = $request->getUri();
        $parameters = $this->router->route($uri->getPath(), $uri->getQuery());
        $controllerSpec = new ControllerSpec(
            sprintf('\%s\controllers\%sController', $this->namespace, Text::ucamelize($parameters['controller'])),
            $parameters['action'], $parameters['controller'], $parameters
            
        );
        unset($parameters['class_name']);
        unset($parameters['action']);
        return $controllerSpec;
    }
    
    /**
     * Create an instance of the controller from the controller specification. 
     * 
     * @param Container $container
     * @param array $controllerSpec
     * @return mixed
     */
    protected function getControllerInstance(Container $container, ControllerSpec $controllerSpec)
    {
        return $container->get($controllerSpec->getControllerClass());
    }
    
    protected function getModelBinders(Container $container): ModelBinderRegistry
    {
        return $container->get(ModelBinderRegistry::class);
    }

    #[\Override]
    public function run(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $container = $this->getServiceContainer($request, $response);
        $this->modelBinders = $this->getModelBinders($container);
        $controllerSpec = $this->getControllerSpec($request);
        $container->bind(ControllerSpec::class)->to(fn() => $controllerSpec);
        $controllerInstance = $this->getControllerInstance($container, $controllerSpec);
        $response = $response->withStatus(200);
        
        $method = $this->getActionMethod($controllerInstance, $controllerSpec, $request);
        
        if ($method === null) {
            throw new NtentanException(
                "Could not resolve a controller/method combination for the current request [{$request->getUri()->getPath()}]."
            );
        }
        
        $argumentDescription = $method->getParameters();
        $arguments = [];
        
        foreach($argumentDescription as $argument) {
            if ($argument->getType()->isBuiltIn() && in_array($argument->getName(), $controllerSpec->identifyParameters())) {
                $arguments[] = $controllerSpec->getParameter($argument->getName());
            } else {
                $arguments[] = $this->bindParameter($argument, $container);
            }
        }
        
        $output = $method->invokeArgs($controllerInstance, $arguments);
        
        return match(true) {
            $output instanceof View => $response->withBody($output->asStream()),
            $output instanceof ResponseInterface => $output,
            gettype($output) === 'string' => $response->withBody(new StringStream($output)),
            default => throw new NtentanException("Controller returned an unexpected " 
                    . ($output === null ? "null output" : "object of type " .get_class($output)))
        };
    }

    /**
     * Bind a value to the container.
     *
     * @param \ReflectionParameter $parameter
     * @param Container $container
     * @return mixed|null
     * @throws \ntentan\panie\exceptions\ResolutionException
     */
    private function bindParameter(\ReflectionParameter $parameter, Container $container)
    {
        $type = $parameter->getType();
        
        // Let's support single named types for now
        if (!($type instanceof \ReflectionNamedType)) {
            return null;
        }

        /** @var ModelBinderInterface $binder */
        $binder = $container->get($this->modelBinders->get($type->getName()));
        return $type->isBuiltin() ?
            $container->get("\${$parameter->getName()}:{$type->getName()}") :
            $binder->bind($container->get($type->getName()), $parameter->getName());
    }
    
    protected function getRouter(): Router
    {
        return $this->router;
    }
    
    private function getActionMethod(object $controller, ControllerSpec $controllerSpec, ServerRequestInterface $request): ?\ReflectionMethod
    {
        $controllerClass = new \ReflectionClass($controller);
        $methods = $controllerClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        $bestScore = 0;
        $bestMethod = null;
        
        foreach ($methods as $method) {
            $currentScore = 0;
            if ($controllerClass->getName() === $method->getDeclaringClass()->getName()) {
                $currentScore += 1;
            }
            $actionAttribute = $method->getAttributes(Action::class);
            $requestedAction = $controllerSpec->getControllerAction();
            
            if (empty($actionAttribute)) {
                continue;
            } else {
                $actionPath = $actionAttribute[0]->newInstance()->getPath();
                if($actionPath === "" && $requestedAction != $method->getName() 
                    || $actionPath !== "" && $actionPath != $requestedAction) 
                {
                    continue;
                }
            }
            
            $attributes = array_reduce(
                self::FILTER_ATTRIBUTES, fn($carry, $x) => array_merge($carry, $method->getAttributes($x)), []
            );

            foreach ($attributes as $attribute) {
                if (!$attribute->newInstance()->match($request)) {
                    $currentScore = -1;
                    break;
                }
                $currentScore++;
            }
            
            if ($currentScore >= $bestScore) {
                $bestMethod = $method;
                $bestScore = $currentScore;
            }
        }
        
        return $bestMethod;
    }
    
    #[\Override]
    public function configure(array $configuration)
    {
        $this->router->setRoutes($configuration['routes']);
    }
    
    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }
    
    protected function getNamespace(): string
    {
        return $this->namespace;
    }
}
