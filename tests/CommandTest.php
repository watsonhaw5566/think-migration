<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CommandTest extends TestCase
{
    public function testVerifyMigrationDirectoryChecksExistence(): void
    {
        $command = $this->createConcreteCommand();

        $nonExistent = sys_get_temp_dir() . '/think_migration_command_test_' . uniqid();

        $this->expectException(InvalidArgumentException::class);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('verifyMigrationDirectory');
        $method->setAccessible(true);
        $method->invoke($command, $nonExistent);
    }

    public function testVerifyMigrationDirectoryAcceptsExistingDir(): void
    {
        $dir = sys_get_temp_dir() . '/think_migration_command_test_ok_' . uniqid();
        mkdir($dir, 0o755, true);

        $command = $this->createConcreteCommand();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('verifyMigrationDirectory');
        $method->setAccessible(true);
        $method->invoke($command, $dir);

        $this->assertTrue(true);

        rmdir($dir);
    }

    public function testAdapterPropertyInitiallyNull(): void
    {
        $command = $this->createConcreteCommand();

        $reflection = new \ReflectionClass($command);
        $adapterProp = $reflection->getProperty('adapter');
        $adapterProp->setAccessible(true);

        $this->assertNull($adapterProp->getValue($command));
    }

    private function createConcreteCommand(): \think\migration\Command
    {
        return new class extends \think\migration\Command {
            protected function configure(): void
            {
                $this->setName('test-command');
            }
        };
    }
}
