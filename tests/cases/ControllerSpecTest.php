<?php

namespace ntentan\mvc\tests\cases;

use ntentan\mvc\ControllerSpec;
use ntentan\mvc\NotFoundController;
use ntentan\mvc\NotFoundControllerSpec;
use PHPUnit\Framework\TestCase;

class ControllerSpecTest extends TestCase
{
    public function testControllerSpecPropertiesAndParameters(): void
    {
        $params = ['id' => 42, 'slug' => 'hello-world'];
        $spec = new ControllerSpec(
            '\\app\\controllers\\UsersController',
            'view',
            'Users',
            $params
        );

        $this->assertSame('\\app\\controllers\\UsersController', $spec->getControllerClass());
        $this->assertSame('view', $spec->getControllerAction());
        $this->assertSame('users', $spec->getControllerName());
        $this->assertSame($params, $spec->getParameters());
        $this->assertSame(['id', 'slug'], $spec->identifyParameters());
        $this->assertSame(42, $spec->getParameter('id'));
        $this->assertSame('hello-world', $spec->getParameter('slug'));
        $this->assertNull($spec->getParameter('missing'));
    }

    public function testControllerSpecDefaultEmptyParameters(): void
    {
        $spec = new ControllerSpec('\\app\\controllers\\HomeController', 'index', 'Home');

        $this->assertSame([], $spec->getParameters());
        $this->assertSame([], $spec->identifyParameters());
        $this->assertNull($spec->getParameter('any'));
    }

    public function testNotFoundControllerSpec(): void
    {
        $spec = new NotFoundControllerSpec();

        $this->assertInstanceOf(ControllerSpec::class, $spec);
        $this->assertSame(NotFoundController::class, $spec->getControllerClass());
        $this->assertSame('main', $spec->getControllerAction());
        $this->assertSame('not_found_controller', $spec->getControllerName());
        $this->assertSame([], $spec->getParameters());
    }
}

