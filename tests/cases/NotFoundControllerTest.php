<?php

namespace ntentan\mvc\tests\cases;

use ntentan\mvc\NotFoundController;
use ntentan\mvc\View;
use PHPUnit\Framework\TestCase;

class NotFoundControllerTest extends TestCase
{
    public function testMainSets404TemplateAndReturnsView(): void
    {
        $view = $this->createMock(View::class);
        $view->expects($this->once())
            ->method('setTemplate')
            ->with('404');

        $controller = new NotFoundController();
        $result = $controller->main($view);

        $this->assertSame($view, $result);
    }
}

