<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use Faker\Factory as FakerFactory;
use PHPUnit\Framework\TestCase;
use think\App;
use think\migration\Factory;

final class HelperTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new App(__DIR__);
        App::setInstance($app);
        $faker = FakerFactory::create();
        $app->instance(Factory::class, new Factory($faker));
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(App::class);
        if ($reflection->hasProperty('instance')) {
            $prop = $reflection->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    public function testDatabasePathWithEmptyPath(): void
    {
        $result = \database_path('');
        $this->assertNotEmpty($result);
        $this->assertStringEndsWith('database', $result);
    }

    public function testDatabasePathWithSubpath(): void
    {
        $result = \database_path('migrations');
        $this->assertStringEndsWith('database' . DIRECTORY_SEPARATOR . 'migrations', $result);
    }

    public function testDatabasePathWithLeadingSeparator(): void
    {
        $result = \database_path(DIRECTORY_SEPARATOR . 'migrations');
        $normalized = 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $this->assertStringEndsWith($normalized, $result);
    }

    public function testDatabasePathWithNestedSubpath(): void
    {
        $result = \database_path('migrations' . DIRECTORY_SEPARATOR . 'foo');
        $this->assertStringEndsWith(
            'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'foo',
            $result
        );
    }

    public function testDatabasePathDoesNotDoubleSeparator(): void
    {
        $result = \database_path('migrations');
        $this->assertDoesNotContainDoubleSeparator($result);
    }

    public function testFactoryFunctionExists(): void
    {
        $this->assertTrue(function_exists('factory'));
    }

    public function testDatabasePathFunctionExists(): void
    {
        $this->assertTrue(function_exists('database_path'));
    }

    private function assertDoesNotContainDoubleSeparator(string $haystack): void
    {
        $double = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
        $this->assertDoesNotMatchRegularExpression('/' . preg_quote($double, '/') . '/', $haystack);
    }
}
