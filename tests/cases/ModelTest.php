<?php

namespace ntentan\mvc\tests\cases;

use ntentan\atiaa\DbContext;
use ntentan\atiaa\Driver;
use ntentan\atiaa\DriverFactoryInterface;
use ntentan\kaikai\Cache;
use ntentan\mvc\Model;
use ntentan\mvc\MvcModelFactory;
use ntentan\mvc\tests\fixtures\models\UserCategories;
use ntentan\nibii\DriverAdapter;
use ntentan\nibii\interfaces\DriverAdapterFactoryInterface;
use ntentan\nibii\interfaces\ValidatorFactoryInterface;
use ntentan\nibii\ORMContext;
use PHPUnit\Framework\TestCase;

class TestableModel extends Model
{
    protected string $table = 'custom_items';
    protected ?string $schema = 'public';
    protected array $hasMany = ['comments'];
    protected array $belongsTo = ['author'];
    protected array $manyHaveMany = ['tags'];

    public function triggerAddError(array &$errors, string $field, string $error): void
    {
        $this->addError($errors, $field, $error);
    }
}

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        $factory = new MvcModelFactory('ntentan\\mvc\\tests\\fixtures');

        $driver = $this->createStub(Driver::class);
        $driver->method('quoteIdentifier')->willReturnCallback(fn(string $id) => "\"$id\"");

        $driverFactory = $this->createStub(DriverFactoryInterface::class);
        $driverFactory->method('createDriver')->willReturn($driver);
        DbContext::initialize($driverFactory);

        $driverAdapter = $this->createStub(DriverAdapter::class);
        $driverAdapterFactory = $this->createStub(DriverAdapterFactoryInterface::class);
        $driverAdapterFactory->method('createDriverAdapter')->willReturn($driverAdapter);

        $validatorFactory = $this->createStub(ValidatorFactoryInterface::class);
        $cache = $this->createStub(Cache::class);

        ORMContext::initialize($factory, $driverAdapterFactory, $validatorFactory, $cache);
    }

    public function testCreateNewAndLoad(): void
    {
        $instance = TestableModel::createNew();
        $this->assertInstanceOf(TestableModel::class, $instance);

        $loaded = Model::load('user_categories');
        $this->assertInstanceOf(UserCategories::class, $loaded);
    }

    public function testGetNameAndGetTable(): void
    {
        $model = new TestableModel();
        $this->assertSame('TestableModel', $model->getName());
        $this->assertSame('"public"."custom_items"', $model->getTable());
    }

    public function testAddError(): void
    {
        $model = new TestableModel();
        $errors = [];

        $model->triggerAddError($errors, 'email', 'Email is required');
        $model->triggerAddError($errors, 'email', 'Email must be valid');
        $model->triggerAddError($errors, 'name', 'Name is too short');

        $this->assertSame([
            'email' => ['Email is required', 'Email must be valid'],
            'name' => ['Name is too short']
        ], $errors);
    }

    public function testSerializeAndUnserialize(): void
    {
        $model = new TestableModel(['id' => 5, 'title' => 'Sample']);
        $serialized = serialize($model);

        /** @var TestableModel $restored */
        $restored = unserialize($serialized);

        $this->assertInstanceOf(TestableModel::class, $restored);
        $this->assertSame(5, $restored['id']);
        $this->assertSame('Sample', $restored['title']);
        $this->assertSame('"public"."custom_items"', $restored->getTable());
    }

    public function testCountWithInMemoryData(): void
    {
        $model = new TestableModel([
            ['id' => 1, 'title' => 'One'],
            ['id' => 2, 'title' => 'Two']
        ]);

        $this->assertSame(2, $model->count());
    }
}

