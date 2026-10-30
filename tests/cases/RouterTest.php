<?php

namespace ntentan\mvc\tests\cases;

use ntentan\exceptions\RouteExistsException;
use ntentan\mvc\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testAppendAndMatchRoute(): void
    {
        $this->router->appendRoute(
            'default',
            '/{controller}/{action}/{id}',
            ['default' => ['controller' => 'home', 'action' => 'index']]
        );

        $matched = $this->router->route('/users/edit/15');

        $this->assertSame('users', $matched['controller']);
        $this->assertSame('edit', $matched['action']);
        $this->assertSame('15', $matched['id']);
    }

    public function testDefaultParametersAppliedWhenOmitted(): void
    {
        $this->router->appendRoute(
            'default',
            '/{controller}/{action}',
            ['default' => ['controller' => 'dashboard', 'action' => 'index']]
        );

        $matchedRoot = $this->router->route('/');
        $this->assertSame('dashboard', $matchedRoot['controller']);
        $this->assertSame('index', $matchedRoot['action']);

        $matchedControllerOnly = $this->router->route('/reports');
        $this->assertSame('reports', $matchedControllerOnly['controller']);
        $this->assertSame('index', $matchedControllerOnly['action']);
    }

    public function testPrependRouteTakesPrecedenceOverAppendedRoute(): void
    {
        $this->router->appendRoute(
            'catch_all',
            '/{controller}/{action}',
            ['default' => ['controller' => 'home', 'action' => 'index']]
        );

        $this->router->prependRoute(
            'admin_login',
            '/admin/login',
            ['default' => ['controller' => 'auth', 'action' => 'admin_login']]
        );

        $matched = $this->router->route('/admin/login');
        $this->assertSame('auth', $matched['controller']);
        $this->assertSame('admin_login', $matched['action']);
    }

    public function testSetRoutesAndGetRoute(): void
    {
        $routes = [
            [
                'name' => 'items',
                'pattern' => '/items/{id}',
                'parameters' => ['default' => ['controller' => 'items', 'action' => 'view']]
            ],
            [
                'name' => 'fallback',
                'pattern' => '/{controller}/{action}',
                'parameters' => ['default' => ['controller' => 'main', 'action' => 'index']]
            ]
        ];

        $this->router->setRoutes($routes);

        $itemRoute = $this->router->getRoute('items');
        $this->assertSame('items', $itemRoute['name']);
        $this->assertSame('/items/{id}', $itemRoute['pattern']);
        $this->assertSame(['id'], $itemRoute['variables']);

        $matched = $this->router->route('/items/99');
        $this->assertSame('items', $matched['controller']);
        $this->assertSame('view', $matched['action']);
        $this->assertSame('99', $matched['id']);
    }

    public function testUrlDecodedRouteMatching(): void
    {
        $this->router->appendRoute(
            'search',
            '/search/{query}',
            ['default' => ['controller' => 'search', 'action' => 'results']]
        );

        $matched = $this->router->route('/search/hello%20world');
        $this->assertSame('hello world', $matched['query']);
    }

    public function testDuplicateRouteThrowsRouteExistsException(): void
    {
        $this->router->appendRoute('home', '/');

        $this->expectException(RouteExistsException::class);
        $this->expectExceptionMessage("A route named 'home' already exists");

        $this->router->appendRoute('home', '/home');
    }

    public function testUnmatchedRouteReturnsNull(): void
    {
        $this->router->appendRoute('strict', '/exact/path');

        $this->assertNull($this->router->route('/unknown/path'));
    }
}

