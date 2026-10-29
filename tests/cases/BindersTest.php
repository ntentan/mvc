<?php

namespace ntentan\mvc\tests\cases;

use ntentan\honam\Templates;
use ntentan\http\Request;
use ntentan\mvc\binders\DefaultModelBinder;
use ntentan\mvc\binders\ModelBinderRegistry;
use ntentan\mvc\binders\ViewBinder;
use ntentan\mvc\ControllerSpec;
use ntentan\mvc\Model;
use ntentan\mvc\View;
use ntentan\nibii\ModelDescription;
use ntentan\utils\Input;
use PHPUnit\Framework\TestCase;

class SampleDto
{
    public string $name = '';
    public string $role = '';
    public int|string $id = 0;
    private string $secret = 'unchanged';

    public function getSecret(): string
    {
        return $this->secret;
    }
}

class BindersTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Input::class);
        $property = $reflection->getProperty('arrays');
        $property->setValue(null, []);
    }

    public function testModelBinderRegistry(): void
    {
        $registry = new ModelBinderRegistry();
        $this->assertNull($registry->getDefaultBinderClass());

        $registry->setDefaultBinderClass(DefaultModelBinder::class);
        $this->assertSame(DefaultModelBinder::class, $registry->getDefaultBinderClass());

        $registry->register(View::class, ViewBinder::class);

        $this->assertSame(ViewBinder::class, $registry->get(View::class));
        $this->assertSame(DefaultModelBinder::class, $registry->get(SampleDto::class));
    }

    public function testDefaultModelBinderBindsPlainObjectWithCorrectPrecedence(): void
    {
        $reflection = new \ReflectionClass(Input::class);
        $property = $reflection->getProperty('arrays');
        $property->setValue(null, [
            INPUT_GET => ['name' => 'FromGet', 'role' => 'Editor'],
            INPUT_POST => ['name' => 'FromPost']
        ]);

        $request = $this->createStub(Request::class);
        $spec = new ControllerSpec(
            '\\app\\controllers\\UsersController',
            'save',
            'users',
            ['id' => 10, 'name' => 'FromRoute', 'role' => 'Viewer', 'secret' => 'hacked']
        );

        $binder = new DefaultModelBinder($request, $spec);
        $dto = new SampleDto();

        /** @var SampleDto $bound */
        $bound = $binder->bind($dto, 'dto');

        $this->assertSame($dto, $bound);
        $this->assertSame(10, $bound->id);
        $this->assertSame('Editor', $bound->role);
        $this->assertSame('FromPost', $bound->name);
        $this->assertSame('unchanged', $bound->getSecret());
    }

    public function testDefaultModelBinderBindsModelFields(): void
    {
        $reflection = new \ReflectionClass(Input::class);
        $property = $reflection->getProperty('arrays');
        $property->setValue(null, [
            INPUT_GET => [],
            INPUT_POST => ['email' => 'user@example.com', 'ignored_field' => 'value']
        ]);

        $description = $this->createStub(ModelDescription::class);
        $description->method('getFields')->willReturn([
            'id' => ['name' => 'id'],
            'email' => ['name' => 'email']
        ]);

        $model = $this->getMockBuilder(Model::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDescription'])
            ->getMock();
        $model->expects($this->atLeastOnce())->method('getDescription')->willReturn($description);

        $request = $this->createStub(Request::class);
        $spec = new ControllerSpec('\\app\\controllers\\UsersController', 'update', 'users', ['id' => 7]);

        $binder = new DefaultModelBinder($request, $spec);
        $bound = $binder->bind($model, 'user');

        $this->assertSame($model, $bound);
        $this->assertSame(7, $bound['id']);
        $this->assertSame('user@example.com', $bound['email']);
        $this->assertNull($bound['ignored_field']);
    }

    public function testViewBinderPrependsPathAndSetsTemplate(): void
    {
        $templates = $this->createMock(Templates::class);
        $spec = new ControllerSpec('\\app\\controllers\\PostsController', 'details', 'Posts');
        $view = $this->createMock(View::class);

        $templates->expects($this->once())
            ->method('prependPath')
            ->with('/var/www/app/views/posts');

        $view->expects($this->once())
            ->method('setTemplate')
            ->with('posts_details.tpl.php');

        $binder = new ViewBinder($templates, $spec, '/var/www/app');
        $result = $binder->bind($view, 'view');

        $this->assertSame($view, $result);
    }
}
