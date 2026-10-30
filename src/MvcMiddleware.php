<?php

namespace ntentan\mvc;

use ntentan\http\filters\RequestFilter;
use ntentan\http\filters\Route;
use ntentan\mvc\exceptions\ControllerActionNotFoundException;
use ntentan\mvc\exceptions\ControllerNotFoundException;
use ntentan\mvc\exceptions\ParameterNotFoundException;
use ntentan\Context;
use ntentan\mvc\binders\ModelBinderInterface;
use ntentan\panie\exceptions\ResolutionException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ntentan\middleware\Middleware;
use ntentan\panie\Container;
use ntentan\utils\Text;
use ntentan\exceptions\NtentanException;
use ntentan\mvc\binders\ModelBinderRegistry;
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

    private string $namespace;

    private Context $context;

    private bool $enableNotFoundController;

    public function __construct(Router $router, ServiceContainerBuilder $containerBuilder, Context $context, string $namespace = 'app')
    {
        $this->router = $router;
        $this->containerBuilder = $containerBuilder;
        $this->context = $context;
        $this->namespace = $namespace;
    }
    
    protected function getServiceContainer(?ServerRequestInterface $request=null, ?ResponseInterface $response=null): ContainerInterface
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

    private function handleControllerNotFound(?ControllerSpec $controllerSpec = null, ?\Throwable $exception = null): ControllerSpec
    {
        $container = $this->getServiceContainer();
        if ($this->enableNotFoundController) {
            return $container->get(NotFoundControllerSpec::class);
        } else {
            $request = $container->get(ServerRequestInterface::class);
            $message = $controllerSpec !== null
                ? <<<MSG
                    Failed to find a controller class for the current request [{$request->getUri()->getPath()}].
                    Class [{$controllerSpec->getControllerClass()}] could not be found.
                    MSG
                : <<<MSG
                    Failed to find a controller class for the current request [{$request->getUri()->getPath()}].
                    MSG;
            throw new ControllerNotFoundException($message, previous: $exception);
        }
    }
    
    /**
     * Returns a structural array with information about the controller class to load, the action method to call, and
     * the parameters to bind to the action method.
     * 
     * @param ServerRequestInterface $request
     * @return ControllerSpec
     */
    protected function getControllerSpec(ServerRequestInterface $request): ControllerSpec
    {
        $uri = $request->getUri();
        $parameters = $this->router->route($uri->getPath());
        if ($parameters === null) {
            $controllerSpec = $this->handleControllerNotFound();
        } else {
            $controllerSpec = new ControllerSpec(
                sprintf('\%s\controllers\%sController', $this->namespace, Text::ucamelize($parameters['controller'])),
                $parameters['action'], $parameters['controller'], $parameters

            );
            unset($parameters['class_name']);
            unset($parameters['action']);
        }
        return $controllerSpec;
    }
    
    /**
     * Create an instance of the controller from the controller specification. 
     * 
     * @param Container $container
     * @param ControllerSpec $controllerSpec
     * @return object
     */
    protected function getControllerInstance(Container $container, ControllerSpec &$controllerSpec): object
    {
        $controllerClass = $controllerSpec->getControllerClass();
        try {
            return $container->get($controllerClass);
        } catch (ResolutionException $exception) {
            $controllerSpec = $this->handleControllerNotFound($controllerSpec, $exception);
            return $container->get($controllerSpec->getControllerClass());
        }
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
            if ($this->enableNotFoundController) {
                $controllerSpec = $container->get(NotFoundControllerSpec::class);
                $container->bind(ControllerSpec::class)->to(fn() => $controllerSpec);
                $controllerInstance = $this->getControllerInstance($container, $controllerSpec);
                $method = $this->getActionMethod($controllerInstance, $controllerSpec, $request);
            } else {
                throw new ControllerActionNotFoundException(
                    <<<MSG
                    Could not resolve an action method for the current request [{$request->getUri()->getPath()}]. 
                    Controller [{$controllerSpec->getControllerClass()}] does not have a method [{$controllerSpec->getControllerAction()}].
                    MSG
                );
            }
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
     * @throws \ntentan\panie\exceptions\ResolutionException|ParameterNotFoundException
     */
    private function bindParameter(\ReflectionParameter $parameter, Container $container): mixed
    {
        $type = $parameter->getType();
        
        // Let's support single named types for now
        if (!($type instanceof \ReflectionNamedType)) {
            return null;
        }

        /** @var ModelBinderInterface $binder */
        $binder = $container->get($this->modelBinders->get($type->getName()));

        try {
            return $type->isBuiltin() ?
                $container->get("\${$parameter->getName()}:{$type->getName()}") :
                $binder->bind($container->get($type->getName()), $parameter->getName());

        } catch (ResolutionException $exception) {
            throw new ParameterNotFoundException($parameter, $exception);
        }
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

            $requestedAction = $controllerSpec->getControllerAction();
            $isMethodValid = false;

            foreach ($method->getAttributes() as $attribute) {
                $attributeName = $attribute->getName();
                $isRouteAttribute = is_a($attributeName, Route::class, true);
                if ($isRouteAttribute) {
                    $attributeInstance = $attribute->newInstance();
                    // Ensure a match on both the method and the
                    if (strtolower($attributeInstance->getMethod()) !== strtolower($request->getMethod())) {
                        continue;
                    }
                    // Ensure a match for the action path allowing the Route path to override
                    $actionPath = $attributeInstance->getRoute();
                    if($actionPath === "" && $requestedAction != $method->getName()
                        && Text::camelize($requestedAction) != $method->getName()
                        || $actionPath !== "" && $actionPath != $requestedAction
                    ) {
                        continue;
                    }
                    $currentScore++;
                    $isMethodValid = true;
                } else if (!$isRouteAttribute && is_a($attributeName, RequestFilter::class, true)) {
                    $attributeInstance = $attribute->newInstance();
                    if ($attributeInstance->match($request)) {
                        $currentScore++;
                        $isMethodValid = true;
                    } else {
                        $currentScore--;
                    }
                } else {
                    $currentScore--;
                }
            }
            
            if ($currentScore >= $bestScore && $isMethodValid) {
                $bestMethod = $method;
                $bestScore = $currentScore;
            }
        }
        
        return $bestMethod;
    }
    
    #[\Override]
    public function configure(array $configuration): void
    {
        $this->enableNotFoundController = $configuration['enable_not_found_controller'] ?? false;
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
