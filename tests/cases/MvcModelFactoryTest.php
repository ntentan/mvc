<?php

namespace ntentan\mvc\tests\cases {

    use ntentan\atiaa\DbContext;
    use ntentan\atiaa\Driver;
    use ntentan\atiaa\DriverFactoryInterface;
    use ntentan\kaikai\Cache;
    use ntentan\mvc\MvcModelFactory;
    use ntentan\nibii\DriverAdapter;
    use ntentan\nibii\interfaces\DriverAdapterFactoryInterface;
    use ntentan\nibii\interfaces\ValidatorFactoryInterface;
    use ntentan\nibii\ORMContext;
    use ntentan\nibii\RecordWrapper;
    use ntentan\nibii\relationships\RelationshipType;
    use PHPUnit\Framework\TestCase;

    class MvcModelFactoryTest extends TestCase
    {
        private MvcModelFactory $factory;

        protected function setUp(): void
        {
            $this->factory = new MvcModelFactory('ntentan\\mvc\\tests\\fixtures');

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

            ORMContext::initialize($this->factory, $driverAdapterFactory, $validatorFactory, $cache);
        }

        public function testLoadRecordWrapper(): void
        {
            $instance = $this->factory->loadRecordWrapper(
                \ntentan\mvc\tests\fixtures\models\UserCategories::class
            );

            $this->assertInstanceOf(RecordWrapper::class, $instance);
            $this->assertInstanceOf(\ntentan\mvc\tests\fixtures\models\UserCategories::class, $instance);
        }

        public function testCreateModelForHasManyAndBelongsTo(): void
        {
            $hasManyModel = $this->factory->createModel('user_categories', RelationshipType::HAS_MANY);
            $this->assertInstanceOf(\ntentan\mvc\tests\fixtures\models\UserCategories::class, $hasManyModel);

            $belongsToModel = $this->factory->createModel('department', RelationshipType::BELONGS_TO);
            $this->assertInstanceOf(\ntentan\mvc\tests\fixtures\models\Departments::class, $belongsToModel);
        }

        public function testGetModelTable(): void
        {
            $instance = new \ntentan\mvc\tests\fixtures\models\UserCategories();
            $this->assertSame('user_categories', $this->factory->getModelTable($instance));
        }

        public function testGetClassName(): void
        {
            $this->assertSame(
                '\\ntentan\\mvc\\tests\\fixtures\\models\\UserCategories',
                $this->factory->getClassName('user_categories')
            );
        }

        public function testGetJunctionClassNameSortsModelNamesAlphabetically(): void
        {
            $junction1 = $this->factory->getJunctionClassName(
                'ntentan\\mvc\\tests\\fixtures\\models\\Users',
                'roles'
            );
            $this->assertSame('ntentan\\mvc\\tests\\fixtures\\models\\RolesUsers', $junction1);

            $junction2 = $this->factory->getJunctionClassName(
                'ntentan\\mvc\\tests\\fixtures\\models\\Authors',
                'books'
            );
            $this->assertSame('ntentan\\mvc\\tests\\fixtures\\models\\AuthorsBooks', $junction2);
        }
    }
}

namespace ntentan\mvc\tests\fixtures\models {

    use ntentan\mvc\Model;

    class UserCategories extends Model
    {
    }

    class Departments extends Model
    {
    }
}
