<?php

namespace ntentan\mvc\tests\cases;

use ntentan\http\filters\Get;
use ntentan\http\Response;
use ntentan\http\StringStream;
use ntentan\mvc\NotFoundController;
use ntentan\mvc\View;
use PHPUnit\Framework\TestCase;

class NotFoundControllerTest extends TestCase
{
    public function testMainSets404TemplateAndReturnsResponse(): void
    {
        $stream = new StringStream('Page not found');
        $view = $this->createMock(View::class);
        $view->expects($this->once())
            ->method('setTemplate')
            ->with('404');
        $view->expects($this->once())
            ->method('asStream')
            ->willReturn($stream);

        $response = new Response();
        $controller = new NotFoundController();
        $result = $controller->main($view, $response);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(404, $result->getStatusCode());
        $this->assertSame('Page not found', (string) $result->getBody());
    }

    public function testMainHasGetAttribute(): void
    {
        $reflectionClass = new \ReflectionClass(NotFoundController::class);
        $mainMethod = $reflectionClass->getMethod('main');
        $attributes = $mainMethod->getAttributes(Get::class);

        $this->assertCount(1, $attributes);
    }
}
