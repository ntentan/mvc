<?php

namespace ntentan\mvc\tests\cases {

    use ntentan\Context;
    use ntentan\exceptions\NtentanException;
    use ntentan\honam\Templates;
    use ntentan\http\Request;
    use ntentan\http\Response;
    use ntentan\http\StringStream;
    use ntentan\http\Uri;
    use ntentan\mvc\exceptions\ControllerActionNotFoundException;
    use ntentan\mvc\exceptions\ControllerNotFoundException;
    use ntentan\mvc\exceptions\ParameterNotFoundException;
    use ntentan\mvc\ControllerSpec;
    use ntentan\mvc\MvcMiddleware;
    use ntentan\mvc\NotFoundControllerSpec;
    use ntentan\mvc\Router;
    use ntentan\mvc\ServiceContainerBuilder;
    use ntentan\mvc\tests\fixtures\controllers\SampleDtoArg;
    use ntentan\mvc\View;
    use ntentan\sessions\SessionStore;
    use PHPUnit\Framework\TestCase;
    use Psr\Container\ContainerInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class InspectableMvcMiddleware extends MvcMiddleware
    {
        public function exposeNamespace(): string
        {
            return $this->getNamespace();
        }

        public function exposeContext(): Context
        {
            return $this->getContext();
        }

        public function exposeRouter(): Router
        {
            return $this->getRouter();
        }

        public function exposeServiceContainer(?ServerRequestInterface $request = null, ?ResponseInterface $response = null): ContainerInterface
        {
            return $this->getServiceContainer($request, $response);
        }

        public function exposeControllerSpec(ServerRequestInterface $request): ControllerSpec
        {
            return $this->getControllerSpec($request);
        }
    }

    class MvcMiddlewareTest extends TestCase
    {
        private Router $router;
        private Context $context;
        private ServiceContainerBuilder $containerBuilder;
        private InspectableMvcMiddleware $middleware;

        protected function setUp(): void
        {
            $this->router = new Router();
            $this->context = $this->createStub(Context::class);
            $this->context->method('getPrefix')->willReturn('');
            $session = $this->createStub(SessionStore::class);

            $templates = $this->createStub(Templates::class);
            $templates->method('render')->willReturnCallback(
                fn(string $tpl, array $data) => $tpl === 'default' ? "<layout>{$data['contents']}</layout>" : "rendered:$tpl"
            );

            $this->containerBuilder = new ServiceContainerBuilder('/tmp/app', $session, $this->context);
            $this->containerBuilder->addBindings([
                Templates::class => [fn() => $templates, 'singleton' => true],
                View::class => fn() => new View($templates, new StringStream()),
            ]);

            $this->middleware = $this->createMiddleware(false);
        }

        private function createMiddleware(bool $enableNotFoundController = false): InspectableMvcMiddleware
        {
            $this->router = new Router();
            $middleware = new InspectableMvcMiddleware(
                $this->router,
                $this->containerBuilder,
                $this->context,
                'ntentan\\mvc\\tests\\fixtures'
            );

            $middleware->configure([
                'enable_not_found_controller' => $enableNotFoundController,
                'routes' => [
                    [
                        'name' => 'with_id',
                        'pattern' => '/{controller}/{action}/{id}',
                        'parameters' => ['default' => ['controller' => 'items', 'action' => 'index']]
                    ],
                    [
                        'name' => 'default',
                        'pattern' => '/{controller}/{action}',
                        'parameters' => ['default' => ['controller' => 'items', 'action' => 'index']]
                    ]
                ]
            ]);

            return $middleware;
        }

        private function createRequest(string $path, string $method = 'GET', array $headers = []): Request
        {
            $uri = new Uri("http://localhost{$path}");
            $request = $this->createStub(Request::class);
            $request->method('getUri')->willReturn($uri);
            $request->method('getMethod')->willReturn($method);
            $request->method('getHeader')->willReturnCallback(fn(string $name) => $headers[$name] ?? []);
            $request->method('getHeaderLine')->willReturnCallback(
                fn(string $name) => isset($headers[$name]) ? implode(', ', $headers[$name]) : ''
            );
            return $request;
        }

        public function testGettersAndNamespaceConfiguration(): void
        {
            $this->assertSame('ntentan\\mvc\\tests\\fixtures', $this->middleware->exposeNamespace());
            $this->assertSame($this->context, $this->middleware->exposeContext());
            $this->assertSame($this->router, $this->middleware->exposeRouter());

            $this->middleware->setNamespace('custom\\ns');
            $this->assertSame('custom\\ns', $this->middleware->exposeNamespace());
        }

        public function testGetServiceContainerCachesAndReturnsContainer(): void
        {
            $request = $this->createRequest('/items/hello');
            $response = new Response();

            $container = $this->middleware->exposeServiceContainer($request, $response);
            $this->assertInstanceOf(ContainerInterface::class, $container);
            $this->assertSame($container, $this->middleware->exposeServiceContainer());
        }

        public function testGetControllerSpecReturnsControllerSpecWhenRouteMatches(): void
        {
            $request = $this->createRequest('/items/hello');
            $spec = $this->middleware->exposeControllerSpec($request);

            $this->assertInstanceOf(ControllerSpec::class, $spec);
            $this->assertSame('ntentan\\mvc\\tests\\fixtures\\controllers\\ItemsController', ltrim($spec->getControllerClass(), '\\'));
            $this->assertSame('hello', $spec->getControllerAction());
        }

        public function testGetControllerSpecReturnsNotFoundControllerSpecWhenNoRouteMatchesAndNotFoundEnabled(): void
        {
            $middleware = $this->createMiddleware(true);
            $request = $this->createRequest('/unmatched/path/with/too/many/segments');
            $middleware->exposeServiceContainer($request, new Response());
            $spec = $middleware->exposeControllerSpec($request);

            $this->assertInstanceOf(NotFoundControllerSpec::class, $spec);
        }

        public function testGetControllerSpecThrowsWhenNoRouteMatchesAndNotFoundDisabled(): void
        {
            $request = $this->createRequest('/unmatched/path/with/too/many/segments');
            $this->middleware->exposeServiceContainer($request, new Response());

            $this->expectException(ControllerNotFoundException::class);
            $this->expectExceptionMessage(
                'Failed to find a controller class for the current request [/unmatched/path/with/too/many/segments].'
            );

            $this->middleware->exposeControllerSpec($request);
        }

        public function testRunDispatchesActionReturningString(): void
        {
            $request = $this->createRequest('/items/hello');
            $response = new Response();

            $result = $this->middleware->run($request, $response, fn($req, $res) => $res);

            $this->assertSame(200, $result->getStatusCode());
            $this->assertSame('Hello World', (string) $result->getBody());
        }

        public function testRunDispatchesActionReturningResponseInterface(): void
        {
            $request = $this->createRequest('/items/custom_response');
            $response = new Response();

            $result = $this->middleware->run($request, $response, fn($req, $res) => $res);

            $this->assertInstanceOf(ResponseInterface::class, $result);
            $this->assertSame(201, $result->getStatusCode());
            $this->assertSame('Created Resource', (string) $result->getBody());
        }

        public function testRunDispatchesActionReturningView(): void
        {
            $request = $this->createRequest('/items/show_view');
            $response = new Response();

            $result = $this->middleware->run($request, $response, fn($req, $res) => $res);

            $this->assertSame(200, $result->getStatusCode());
            $this->assertSame('<layout>rendered:items_show_view.tpl.php</layout>', (string) $result->getBody());
        }

        public function testRunBindsBuiltInRouteParametersAndModelBinderObjects(): void
        {
            $request = $this->createRequest('/items/inspect/77');
            $response = new Response();

            $result = $this->middleware->run($request, $response, fn($req, $res) => $res);

            $this->assertSame('Item 77 (bound=77)', (string) $result->getBody());
        }

        public function testRunSelectsActionByHttpMethodAndRequestFilterScore(): void
        {
            $postRequest = $this->createRequest('/items/submit', 'POST');
            $postResult = $this->middleware->run($postRequest, new Response(), fn($req, $res) => $res);
            $this->assertSame('Submitted via POST', (string) $postResult->getBody());

            $jsonPostRequest = $this->createRequest('/items/submit', 'POST', ['X-Format' => ['json']]);
            $jsonResult = $this->middleware->run($jsonPostRequest, new Response(), fn($req, $res) => $res);
            $this->assertSame('Submitted via POST JSON', (string) $jsonResult->getBody());
        }

        public function testRunSelectsActionWithCustomRoutePathOverride(): void
        {
            $request = $this->createRequest('/items/aliased');
            $result = $this->middleware->run($request, new Response(), fn($req, $res) => $res);

            $this->assertSame('Custom Route Alias Matched', (string) $result->getBody());
        }

        public function testRunThrowsControllerNotFoundExceptionWhenControllerMissing(): void
        {
            $request = $this->createRequest('/unknown/index');

            $this->expectException(ControllerNotFoundException::class);
            $this->expectExceptionMessage(
                "Failed to find a controller class for the current request [/unknown/index].\n" .
                "Class [\\ntentan\\mvc\\tests\\fixtures\\controllers\\UnknownController] could not be found."
            );

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunThrowsControllerNotFoundExceptionWhenNoRouteMatches(): void
        {
            $request = $this->createRequest('/unmatched/path/with/too/many/segments');

            $this->expectException(ControllerNotFoundException::class);
            $this->expectExceptionMessage(
                'Failed to find a controller class for the current request [/unmatched/path/with/too/many/segments].'
            );

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunThrowsControllerActionNotFoundExceptionWhenActionMissing(): void
        {
            $request = $this->createRequest('/items/missing_action');

            $this->expectException(ControllerActionNotFoundException::class);

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunThrowsControllerActionNotFoundExceptionWhenHttpMethodDoesNotMatch(): void
        {
            $request = $this->createRequest('/items/hello', 'POST');

            $this->expectException(ControllerActionNotFoundException::class);

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunThrowsControllerActionNotFoundExceptionWhenMethodLacksRouteAttributes(): void
        {
            $request = $this->createRequest('/items/unannotated_method');

            $this->expectException(ControllerActionNotFoundException::class);

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunRoutesToNotFoundControllerWhenControllerNotFound(): void
        {
            $middleware = $this->createMiddleware(true);
            $request = $this->createRequest('/unknown/index');
            $result = $middleware->run($request, new Response(), fn($req, $res) => $res);

            $this->assertSame(404, $result->getStatusCode());
            $this->assertSame('<layout>rendered:404</layout>', (string) $result->getBody());
        }

        public function testRunRoutesToNotFoundControllerWhenActionNotFound(): void
        {
            $middleware = $this->createMiddleware(true);
            $request = $this->createRequest('/items/missing_action');
            $result = $middleware->run($request, new Response(), fn($req, $res) => $res);

            $this->assertSame(404, $result->getStatusCode());
            $this->assertSame('<layout>rendered:404</layout>', (string) $result->getBody());
        }

        public function testRunRoutesToNotFoundControllerWhenActionHttpMethodDoesNotMatch(): void
        {
            $middleware = $this->createMiddleware(true);
            // /items/submit is only mapped for POST
            $request = $this->createRequest('/items/submit', 'GET');
            $result = $middleware->run($request, new Response(), fn($req, $res) => $res);

            $this->assertSame(404, $result->getStatusCode());
            $this->assertSame('<layout>rendered:404</layout>', (string) $result->getBody());
        }

        public function testRunRoutesToNotFoundControllerWhenMethodLacksRouteAttributes(): void
        {
            $middleware = $this->createMiddleware(true);
            $request = $this->createRequest('/items/unannotated_method', 'GET');
            $result = $middleware->run($request, new Response(), fn($req, $res) => $res);

            $this->assertSame(404, $result->getStatusCode());
            $this->assertSame('<layout>rendered:404</layout>', (string) $result->getBody());
        }

        public function testRunRoutesToNotFoundControllerWhenNoRouteMatches(): void
        {
            $middleware = $this->createMiddleware(true);
            $request = $this->createRequest('/unmatched/path/with/too/many/segments');
            $result = $middleware->run($request, new Response(), fn($req, $res) => $res);

            $this->assertSame(404, $result->getStatusCode());
            $this->assertSame('<layout>rendered:404</layout>', (string) $result->getBody());
        }

        public function testRunThrowsParameterNotFoundExceptionWhenParameterMissing(): void
        {
            $request = $this->createRequest('/items/needs_missing_param');

            $this->expectException(ParameterNotFoundException::class);
            $this->expectExceptionMessage('Could not find a value to bind to [$unresolvableParam]');

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunThrowsNtentanExceptionWhenControllerReturnsNull(): void
        {
            $request = $this->createRequest('/items/returns_null');

            $this->expectException(NtentanException::class);
            $this->expectExceptionMessage('Controller returned an unexpected null output');

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }

        public function testRunThrowsNtentanExceptionWhenControllerReturnsUnexpectedObject(): void
        {
            $request = $this->createRequest('/items/returns_object');

            $this->expectException(NtentanException::class);
            $this->expectExceptionMessage('Controller returned an unexpected object of type stdClass');

            $this->middleware->run($request, new Response(), fn($req, $res) => $res);
        }
    }
}

namespace ntentan\mvc\tests\fixtures\controllers {

    use ntentan\http\filters\Get;
    use ntentan\http\filters\Header;
    use ntentan\http\filters\Post;
    use ntentan\http\Response;
    use ntentan\http\StringStream;
    use ntentan\mvc\View;
    use Psr\Http\Message\ResponseInterface;

    class SampleDtoArg
    {
        public string $id = '';
    }

    class ItemsController
    {
        #[Get]
        public function hello(): string
        {
            return 'Hello World';
        }

        #[Get]
        public function customResponse(): ResponseInterface
        {
            return (new Response())->withStatus(201)->withBody(new StringStream('Created Resource'));
        }

        #[Get]
        public function showView(View $view): View
        {
            return $view;
        }

        #[Get]
        public function inspect(string $id, SampleDtoArg $dto): string
        {
            return "Item {$id} (bound={$dto->id})";
        }

        #[Post('submit')]
        public function submitDefault(): string
        {
            return 'Submitted via POST';
        }

        #[Post('submit')]
        #[Header('X-Format', 'json')]
        public function submitJson(): string
        {
            return 'Submitted via POST JSON';
        }

        #[Get('aliased')]
        public function actualMethodName(): string
        {
            return 'Custom Route Alias Matched';
        }

        #[Get]
        public function needsMissingParam(int $unresolvableParam): string
        {
            return (string) $unresolvableParam;
        }

        #[Get]
        public function returnsNull(): mixed
        {
            return null;
        }

        #[Get]
        public function returnsObject(): object
        {
            return new \stdClass();
        }

        public function unannotatedMethod(): string
        {
            return 'Should not be routed';
        }
    }
}

