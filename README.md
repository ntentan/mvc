# Ntentan MVC

`ntentan/mvc` is the Model-View-Controller (MVC) middleware component for the [Ntentan](https://github.com/ntentan/ntentan) PHP framework. It connects URL routing, attribute-driven controller dispatching, automatic parameter and model binding, [Nibii](https://github.com/ntentan/nibii) ORM models, and [Honam](https://github.com/ntentan/honam) template views into a cohesive request-handling pipeline.

---

## Features

- **PSR-15-style Middleware Integration**: Plugs directly into the Ntentan middleware pipeline via `MvcMiddleware`.
- **Flexible Pattern Routing**: Map URL patterns with named placeholders and default parameters to controllers and actions.
- **Attribute-Driven Controller Actions**: Route requests by HTTP method (`#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`) and score candidate actions with request filters (`#[Header]`, `#[MimeHeader]`).
- **Automatic Parameter & Model Binding**: Inject route parameters, services from the DI container (`ntentan/panie`), `View` instances, DTOs, and `Model` records directly into controller action methods.
- **Integrated View Layer**: Wraps `ntentan/honam` templates with automatic controller/action template resolution and layout wrapping.
- **Active Record Models**: Extends `ntentan/nibii` with static query helpers and convention-based namespace/table resolution via `MvcModelFactory`.
- **Configurable 404 Fallback**: Built-in `NotFoundController` fallback or strict routing exceptions (`ControllerNotFoundException`, `ControllerActionNotFoundException`).

---

## Installation

Install `ntentan/mvc` via [Composer](https://getcomposer.org/):

```bash
composer require ntentan/mvc
```

---

## Quick Start & Configuration

Register the MVC service wiring and `MvcMiddleware` with Ntentan's `ApplicationBuilder`:

```php
<?php

use ntentan\ApplicationBuilder;
use ntentan\mvc\Mvc;
use ntentan\mvc\MvcMiddleware;
use ntentan\panie\Container;

require 'vendor/autoload.php';

$container = new Container();
$container->provide('string', 'home')->with(fn() => __DIR__);
$container->provide('array', 'ntentanConfig')->with(fn() => [
    'db' => [
        'driver'   => 'postgresql',
        'host'     => 'localhost',
        'user'     => 'postgres',
        'password' => 'secret',
        'dbname'   => 'my_app',
    ]
]);

$builder = new ApplicationBuilder($container);
$builder
    ->withNamespace('app')
    ->addServices(Mvc::configure('app'))
    ->addMiddlewarePipeline('default', [
        [
            MvcMiddleware::class,
            [
                'enable_not_found_controller' => true,
                'routes' => [
                    [
                        'name' => 'default',
                        'pattern' => '/{controller}/{action}/{id}',
                        'parameters' => [
                            'default' => ['controller' => 'home', 'action' => 'index']
                        ]
                    ]
                ]
            ]
        ]
    ]);

// Initialize Nibii ORM and Atiaa database contexts if using database models
Mvc::initializeDatabase($container);

$app = $builder->build();
$app->execute();
```

### Custom Service Bindings

You can pass custom container bindings as the second argument to `Mvc::configure()`:

```php
$wiring = Mvc::configure('app', [
    MyServiceInterface::class => [MyService::class, 'singleton' => true],
]);
```

---

## Routing

Routes translate incoming request paths into a `controller`, `action`, and any extracted route parameters.

```php
use ntentan\mvc\Router;

$router = new Router();

// Append a route to the end of the routing table
$router->appendRoute(
    'default',
    '/{controller}/{action}/{id}',
    ['default' => ['controller' => 'home', 'action' => 'index']]
);

// Prepend a higher-priority route to the beginning of the routing table
$router->prependRoute(
    'login',
    '/login',
    ['default' => ['controller' => 'auth', 'action' => 'login']]
);
```

---

## Controllers & Actions

Controllers reside in the `\{namespace}\controllers\` namespace and end with the `Controller` suffix (e.g., `\app\controllers\UsersController`).

Action methods are decorated with HTTP route attributes from `ntentan\http\filters\`:
- `#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`
- `#[Header('X-Header-Name', 'expected-value')]`
- `#[MimeHeader('Accept', 'application/json')]`

```php
<?php

namespace app\controllers;

use ntentan\http\filters\Get;
use ntentan\http\filters\Header;
use ntentan\http\filters\Post;
use ntentan\http\Response;
use ntentan\mvc\View;
use app\models\Users;

class UsersController
{
    #[Get]
    public function index(View $view): View
    {
        $view->set('users', Users::fetch());
        return $view;
    }

    #[Get]
    public function profile(int $id, View $view): View
    {
        $view->set('userId', $id);
        return $view;
    }

    #[Post('create')]
    public function createUser(Users $user, Response $response): Response
    {
        $user->save();
        return $response->withStatus(201);
    }

    #[Get('status')]
    #[Header('X-Requested-With', 'XMLHttpRequest')]
    public function ajaxStatus(): string
    {
        return '{"status":"ok"}';
    }
}
```

### Action Return Types

Controller actions may return:
1. **`ntentan\mvc\View`**: Rendered via `View::asStream()` into the HTTP response body.
2. **`Psr\Http\Message\ResponseInterface`**: Returned directly as the HTTP response.
3. **`string`**: Wrapped in a `StringStream` and attached to the HTTP response body.

---

## Model Binding

When an action declares a typed parameter, `MvcMiddleware` resolves it automatically:
- **Built-in types (`int`, `string`, etc.)**: Matched by parameter name against extracted route parameters, or resolved as named parameters from the service container.
- **`ntentan\mvc\View`**: Initialized by `ViewBinder` with template paths pointing to `{$home}/views/{controller}` and a default template of `{controller}_{action}.tpl.php`.
- **Models & DTOs**: Bound by `DefaultModelBinder`, which populates public properties (or `Model` schema fields) in order of precedence:
  1. Route parameters
  2. Query string parameters (`GET`)
  3. Request body parameters (`POST`)

### Custom Model Binders

You can register custom binders for specific types via `ModelBinderRegistry` by implementing `ModelBinderInterface`:

```php
use ntentan\mvc\binders\ModelBinderInterface;

class CustomBinder implements ModelBinderInterface
{
    public function bind(mixed $instance, string $name): mixed
    {
        // Populate and return $instance
        return $instance;
    }
}
```

---

## Views

The `View` class wraps `ntentan/honam` template rendering with a two-step template + layout pipeline:

- **Controller templates**: Looked up in `{$home}/views/{controller}/{controller}_{action}.tpl.php` (as well as `{$home}/views/shared`).
- **Layouts**: Looked up in `{$home}/views/layouts/` (defaults to `default`). The rendered template output is passed to the layout as the `$contents` variable.

```php
#[Get]
public function dashboard(View $view): View
{
    $view->set('title', 'Dashboard');
    $view->set(['metric' => 42, 'active' => true]);

    // Optionally change or disable the layout:
    // $view->setLayout('admin');
    // $view->setLayout(''); // disables layout wrapping

    // Optionally override the template:
    // $view->setTemplate('custom_template.tpl.php');

    return $view;
}
```

---

## Models

Application models live in `\{namespace}\models\` and extend `ntentan\mvc\Model` (which extends `ntentan\nibii\RecordWrapper`):

```php
<?php

namespace app\models;

use ntentan\mvc\Model;

class Users extends Model
{
    protected array $belongsTo = ['department'];
    protected array $hasMany = ['posts'];
}
```

`Model` provides static convenience methods backed by `ORMContext` and `MvcModelFactory`:

```php
// Create a new model instance
$user = Users::createNew();

// Load a model by name
$departments = Model::load('departments');

// Call Nibii query methods statically
$activeUsers = Users::filter('active = ?', true)->fetch();
```

---

## Testing

Run the PHPUnit test suite with:

```bash
vendor/bin/phpunit
```

---

## License

This project is licensed under the MIT License. See [`composer.json`](composer.json) for details.

