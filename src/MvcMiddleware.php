<?php

namespace ntentan\mvc;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ntentan\Middleware;
use ntentan\panie\Container;
use ntentan\utils\Text;
use ntentan\exceptions\NtentanException;
use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\attributes\Action;
use ntentan\mvc\attributes\Method;
use ntentan\http\StringStream;

/**
 * Responds to requests by initializing classes according to an MVC pattern.
 */
class MvcMiddleware implements Middleware
{
    
    private Router $router;
    
    private ServiceContainerBuilder $containerBuilder;
    
    private ModelBinderRegistry $modelBinders;
    
    private Container $serviceContainer;

    private string $namespace = 'app';

    public function __construct(Router $router, ServiceContainerBuilder $containerBuilder)
    {
        $this->router = $router;
        $this->containerBuilder = $containerBuilder;
    }
    
    protected function getServiceContainer(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(!isset($this->serviceContainer)) {
            $this->serviceContainer = $this->containerBuilder->getContainer($request, $response);
        }
        return $this->serviceContainer;
    }
    
    /**
     * Returns a structural array with information about the controller class to load, the action method to call, and
     * the parameters to bind to the action method.
     * 
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function getControllerSpec(ServerRequestInterface $request): array
    {
        $uri = $request->getUri();
        $parameters = $this->router->route($uri->getPath(), $uri->getQuery());
        $parameters['class_name'] = sprintf(
            '\%s\controllers\%sController', $this->namespace, Text::ucamelize($parameters['controller'])
        );        
        return $parameters;
    }
    
    protected function getControllerInstance(Container $container, array $controllerSpec)
    {
        return $container->get($controllerSpec['class_name']);
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
        $controllerClassName = $controllerSpec['class_name'];
        $controllerInstance = $this->getControllerInstance($container, $controllerSpec);
        $response = $response->withStatus(200);
        $methods = $this->getMethods($controllerInstance, $controllerClassName);
        $methodKey = "{$controllerSpec['action']}." . strtolower($request->getMethod());
        unset($controllerSpec['class_name']);
        $routeParameters = array_keys($controllerSpec);
        
        if (isset($methods[$methodKey])) {
            $method = $methods[$methodKey];
            $callable = new \ReflectionMethod($controllerInstance, $method['name']);
            $argumentDescription = $callable->getParameters();
            $arguments = [];
            
            foreach($argumentDescription as $argument) {
                if ($argument->getType()->isBuiltIn() && in_array($argument->getName(), $routeParameters)) {
                    $arguments[] = $controllerSpec[$argument->getName()];
                } else {
                    $arguments[] = $this->bindParameter($argument, $controllerSpec, $container);
                }
            }
            
            $output = $callable->invokeArgs($controllerInstance, $arguments);
            
            return match(true) {
                $output instanceof View => $response->withBody($output->asStream()),
                $output instanceof ResponseInterface => $output,
                gettype($output) === 'string' => $response->withBody(new StringStream($output)),
                default => throw new NtentanException("Controller returned an unexpected " 
                        . ($output === null ? "null output" : "object of type " .get_class($output)))
            };
        }
        
        throw new NtentanException(
            "Could not resolve a controller/method combination for the current request [{$request->getUri()->getPath()}]."
        );
    }
    
    private function bindParameter(\ReflectionParameter $parameter, array $route, Container $container)
    {
        $type = $parameter->getType();
        
        // Let's support single named types for now
        if (!($type instanceof \ReflectionNamedType)) {
            return null;
        }
        
        $binder = $container->get($this->modelBinders->get($type->getName()));
        $binderData = [];
        
        foreach($binder->getRequirements() as $required) {
            $binderData[$required] = match($required) {
                'instance' => $container->get($type->getName()),
                'route' => $route,
                default => throw new NtentanException("Cannot satisfy data binding requirement: {$required}")
            };
        }
        
        return $binder->bind($binderData);
    }
    
    protected function getRouter(): Router
    {
        return $this->router;
    }
    
    private function getMethods(object $controller, string $className): array
    {
        $methods = (new \ReflectionClass($controller))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $results = [];
        foreach ($methods as $method) {
            $methodName = $method->getName();

            // Skip internal methods
            if (substr($methodName, 0, 2) == '__') {
                continue;
            }
            $action = $methodName;
            $requestMethod = ".get";

            foreach ($method->getAttributes() as $attribute) {
                match($attribute->getName()) {
                    Action::class => $action = $attribute->newInstance()->getPath(),
                    Method::class => $requestMethod = "." . strtolower($attribute->newInstance()->getType()),
                    default => null
                };
            }

            $methodKey = $action . $requestMethod;
            if (isset($results[$methodKey]) && $method->class != $className) {
                continue;
            }

            $results[$methodKey] = [
                'name' => $methodName
            ];
        }
        return $results;
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
