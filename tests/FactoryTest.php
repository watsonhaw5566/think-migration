<?php
declare(strict_types=1);

namespace think\migration\Tests;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use PHPUnit\Framework\TestCase;
use think\migration\Factory;

final class FactoryTest extends TestCase
{
    private Faker $faker;
    private Factory $factory;

    protected function setUp(): void
    {
        $this->faker   = FakerFactory::create();
        $this->factory = new Factory($this->faker);
    }

    public function testDefineRegistersDefinition(): void
    {
        $this->factory->define(FakeEntity::class, function (Faker $faker) {
            return ['name' => $faker->name];
        });

        $attributes = $this->factory->raw(FakeEntity::class);

        $this->assertArrayHasKey('name', $attributes);
    }

    public function testDefineAsCreatesNamedDefinition(): void
    {
        $this->factory->defineAs(FakeEntity::class, 'admin', function () {
            return ['role' => 'admin'];
        });

        $attributes = $this->factory->rawOf(FakeEntity::class, 'admin');

        $this->assertSame('admin', $attributes['role']);
    }

    public function testStateAddsStateAttributes(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Default User'];
        });

        $this->factory->state(FakeEntity::class, 'suspended', ['suspended' => true]);

        $attributes = $this->factory->of(FakeEntity::class)->states(['suspended'])->raw();

        $this->assertSame('Default User', $attributes['name']);
        $this->assertTrue($attributes['suspended']);
    }

    public function testStateWithCallable(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'User'];
        });

        $this->factory->state(FakeEntity::class, 'vip', function (Faker $faker) {
            return ['tier' => 'gold'];
        });

        $attributes = $this->factory->of(FakeEntity::class)->states(['vip'])->raw();

        $this->assertSame('gold', $attributes['tier']);
    }

    public function testRawReturnsAttributes(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Alice', 'email' => 'alice@example.com'];
        });

        $attributes = $this->factory->raw(FakeEntity::class);

        $this->assertSame('Alice', $attributes['name']);
        $this->assertSame('alice@example.com', $attributes['email']);
    }

    public function testRawWithOverrideAttributes(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Original', 'role' => 'user'];
        });

        $attributes = $this->factory->raw(FakeEntity::class, ['name' => 'Overridden']);

        $this->assertSame('Overridden', $attributes['name']);
        $this->assertSame('user', $attributes['role']);
    }

    public function testRawOfUsesNamedDefinition(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['role' => 'user'];
        });

        $this->factory->defineAs(FakeEntity::class, 'manager', function () {
            return ['role' => 'manager'];
        });

        $attributes = $this->factory->rawOf(FakeEntity::class, 'manager');

        $this->assertSame('manager', $attributes['role']);
    }

    public function testOfReturnsFactoryBuilder(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Test'];
        });

        $builder = $this->factory->of(FakeEntity::class);

        $this->assertInstanceOf(\think\migration\FactoryBuilder::class, $builder);
    }

    public function testOfWithNamedDefinition(): void
    {
        $this->factory->defineAs(FakeEntity::class, 'custom', function () {
            return ['value' => 42];
        });

        $attributes = $this->factory->of(FakeEntity::class, 'custom')->raw();

        $this->assertSame(42, $attributes['value']);
    }

    public function testLoadFromDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/factory_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $factoryFile = $tmpDir . '/factory_definition_' . time() . '.php';
        file_put_contents($factoryFile, '<?php
$GLOBALS["__factory_loaded"] = true;
');

        $this->factory->load($tmpDir . '/');

        $this->assertTrue($GLOBALS['__factory_loaded']);

        unset($GLOBALS['__factory_loaded']);
        unlink($factoryFile);
        rmdir($tmpDir);
    }

    public function testLoadWithNonExistentDirectoryIsSilent(): void
    {
        $result = $this->factory->load('/non/existent/path/');

        $this->assertSame($this->factory, $result);
    }

    public function testArrayAccessOffsetExists(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Test'];
        });

        $this->assertTrue(isset($this->factory[FakeEntity::class]));
        $this->assertFalse(isset($this->factory['NonExistentClass']));
    }

    public function testArrayAccessOffsetSetAndGet(): void
    {
        $this->factory[FakeEntity::class] = function () {
            return ['foo' => 'bar'];
        };

        $instance = $this->factory[FakeEntity::class];

        $this->assertInstanceOf(FakeEntity::class, $instance);
    }

    public function testArrayAccessOffsetUnset(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Test'];
        });

        $this->assertTrue(isset($this->factory[FakeEntity::class]));

        unset($this->factory[FakeEntity::class]);

        $this->assertFalse(isset($this->factory[FakeEntity::class]));
    }

    public function testMakeReturnsNonNullResult(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Alice'];
        });

        $result = $this->factory->make(FakeEntity::class);

        $this->assertNotNull($result);
        $this->assertInstanceOf(FakeEntity::class, $result);
    }

    public function testMakeAsUsesNamedDefinition(): void
    {
        $this->factory->defineAs(FakeEntity::class, 'guest', function () {
            return ['name' => 'Guest'];
        });

        $result = $this->factory->makeAs(FakeEntity::class, 'guest');

        $this->assertNotNull($result);
        $this->assertInstanceOf(FakeEntity::class, $result);
    }

    public function testCreateReturnsNonNullResult(): void
    {
        $this->factory->define(FakeEntity::class, function () {
            return ['name' => 'Test'];
        });

        $result = $this->factory->of(FakeEntity::class)->raw();

        $this->assertArrayHasKey('name', $result);
    }

    public function testCreateAsUsesNamedDefinition(): void
    {
        $this->factory->defineAs(FakeEntity::class, 'admin', function () {
            return ['role' => 'admin'];
        });

        $result = $this->factory->of(FakeEntity::class, 'admin')->raw();

        $this->assertSame('admin', $result['role']);
    }

    public function testFactoryImplementsArrayAccess(): void
    {
        $this->assertInstanceOf(\ArrayAccess::class, $this->factory);
    }
}