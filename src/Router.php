<?php
namespace ntentan\mvc;

use ntentan\exceptions\RouteExistsException;
use ntentan\exceptions\RouteNotAvailableException;

/**
 * Provides default routing logic that loads controllers based on URLs passed to the framework.
 */
class Router
{

    /**
     * The routing table.
     * An array of regular expressions and associated operations. If a particular
     * request sent in through the URL matches a regular expression in the table,
     * the associated operations are executed.
     *
     * @var array
     */
    private array $routes = [];


    /**
     * Names of all routes added to the routing table.
     * @var array
     */
    private array $routeOrder = [];

    /**
     * Invoke the router to load a route.
     *
     * @param string $route
     * @return array
     * @throws RouteNotAvailableException
     */
    public function route(string $route): array
    {
        // Go through predefined routes till a match is found
        foreach ($this->routeOrder as $routeName) {
            $routeDescription = $this->routes[$routeName];
            $parameters = $this->match($route, $routeDescription);
            if ($parameters !== false) {
                return $this->fillInDefaultParameters($routeDescription, $parameters);
            }
        }

        // We didn't find a match throw an exception
        throw new RouteNotAvailableException("Failed to find a route for the requested path \"{$path}\"");
    }

    private function fillInDefaultParameters($routeDescription, $parameters)
    {
        foreach ($routeDescription['parameters']['default'] ?? [] as $parameter => $value) {
            if (!isset($parameters[$parameter])) {
                $parameters[$parameter] = $value;
            }
        }
        return $parameters;
    }

    private function match($route, $description): array|false
    {
        $parameters = [];
        if (preg_match("|^{$description['regexp']}$|i", urldecode($route), $matches)) {
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $parameters[$key] = $value;
                }
            }
            return $parameters;
        }
        return false;
    }

    private function createRouteRegex($name, $pattern, $parameters)
    {
        $variables = null;
        if(isset($this->routes[$name])) {
            throw new RouteExistsException("A route named '$name' already exists");
        }
        // Generate a PCRE regular expression from pattern
        $regexp = preg_replace_callback(
            "/{(?<prefix>\*|\#)?(?<name>[a-z_][a-zA-Z0-9\_]*)}/", function ($matches) use (&$variables) {
                $variables[] = $matches['name'];
                return sprintf(
                    "(?<{$matches['name']}%s>[a-z0-9_.~:#[\]@!$&'()*+,;=%s\s-]+)?",
                    $matches['prefix'] == '#' ? '____array' : null, $matches['prefix'] != '' ? "\-/_" : null
                );
            }, str_replace('/', '(/)+', $pattern)
        );

        $this->routes[$name] = [
            'name' => $name,
            'pattern' => $pattern,
            'regexp' => $regexp,
            'parameters' => $parameters,
            'variables' => $variables
        ];
    }

    public function appendRoute($name, $pattern, $parameters = [])
    {
        $this->createRouteRegex($name, $pattern, $parameters);
        $this->routeOrder[] = $name;
    }

    public function prependRoute($name, $pattern, $parameters = [])
    {
        $this->createRouteRegex($name, $pattern, $parameters);
        array_unshift($this->routeOrder, $name);
    }

    /**
     * Get the details for a particular route.
     * Details are returned by reference so modifications can be made before the router runs.
     *
     * @param $name
     * @return mixed
     */
    public function getRoute($name)
    {
        return $this->routes[$name];
    }

    public function setRoutes($routes)
    {
        foreach($routes as $route) {
            $this->createRouteRegex($route['name'], $route['pattern'], $route['parameters']);
            $this->routeOrder[] = $route['name'];
        }
    }
}

