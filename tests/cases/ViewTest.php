<?php

namespace ntentan\mvc\tests\cases;

use ntentan\honam\Templates;
use ntentan\http\StringStream;
use ntentan\mvc\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    public function testSetAndGetVariables(): void
    {
        $templates = $this->createStub(Templates::class);
        $stream = new StringStream();
        $view = new View($templates, $stream);

        $view->set('title', 'Welcome');
        $view->set(['author' => 'Ekow', 'year' => 2026]);

        $this->assertSame('Welcome', $view->get('title'));
        $this->assertSame('Ekow', $view->get('author'));
        $this->assertSame(2026, $view->get('year'));
        $this->assertNull($view->get('non_existent'));
    }

    public function testAsStreamRendersTemplateAndDefaultLayout(): void
    {
        $templates = $this->createMock(Templates::class);
        $stream = new StringStream();
        $view = new View($templates, $stream);

        $view->setTemplate('users_index.tpl.php');
        $view->set('username', 'alice');

        $templates->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function (string $template, array $data): string {
                if ($template === 'users_index.tpl.php') {
                    $this->assertSame(['username' => 'alice'], $data);
                    return '<p>Hello alice</p>';
                }
                if ($template === 'default') {
                    $this->assertSame([
                        'username' => 'alice',
                        'contents' => '<p>Hello alice</p>'
                    ], $data);
                    return '<html><body><p>Hello alice</p></body></html>';
                }
                $this->fail("Unexpected template rendered: $template");
            });

        $resultStream = $view->asStream();
        $this->assertSame($stream, $resultStream);
        $this->assertSame('<html><body><p>Hello alice</p></body></html>', (string) $resultStream);
    }

    public function testAsStreamWithCustomLayout(): void
    {
        $templates = $this->createMock(Templates::class);
        $stream = new StringStream();
        $view = new View($templates, $stream);

        $view->setTemplate('dashboard.tpl.php');
        $view->setLayout('admin');

        $templates->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function (string $template, array $data): string {
                return match ($template) {
                    'dashboard.tpl.php' => 'Dashboard Body',
                    'admin' => "[Admin: {$data['contents']}]",
                };
            });

        $this->assertSame('[Admin: Dashboard Body]', (string) $view->asStream());
    }

    public function testAsStreamWithoutLayout(): void
    {
        $templates = $this->createMock(Templates::class);
        $stream = new StringStream();
        $view = new View($templates, $stream);

        $view->setTemplate('partial.tpl.php');
        $view->setLayout('');

        $templates->expects($this->once())
            ->method('render')
            ->with('partial.tpl.php', [])
            ->willReturn('Partial Content');

        $this->assertSame('Partial Content', (string) $view->asStream());
    }

    public function testAsStreamReturnsExistingStreamWhenAlreadyPopulated(): void
    {
        $templates = $this->createMock(Templates::class);
        $templates->expects($this->never())->method('render');

        $stream = new StringStream('Pre-existing output');
        $view = new View($templates, $stream);

        $this->assertSame('Pre-existing output', (string) $view->asStream());
    }
}

