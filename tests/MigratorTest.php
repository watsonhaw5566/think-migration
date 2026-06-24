<?php
declare(strict_types=1);

namespace think\migration\Tests;

use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use think\migration\db\Table;
use think\migration\Migrator;

final class MigratorTest extends TestCase
{
    private Migrator $migrator;

    protected function setUp(): void
    {
        $adapter = AdapterFactory::instance()->getAdapter('sqlite', [
            'name'   => ':memory:',
            'memory' => true,
        ]);

        $this->migrator = new Migrator('test_migration', (int) date('YmdHis'));

        $reflection = new \ReflectionClass($this->migrator);
        $parentRef = $reflection->getParentClass();
        $adapterProp = $parentRef->getProperty('adapter');
        $adapterProp->setAccessible(true);
        $adapterProp->setValue($this->migrator, $adapter);

        $envProp = $parentRef->getProperty('environment');
        $envProp->setAccessible(true);
        $envProp->setValue($this->migrator, 'production');
    }

    public function testTableReturnsThinkTableInstance(): void
    {
        $table = $this->migrator->table('users');

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testTableSetsTableName(): void
    {
        $table = $this->migrator->table('orders');

        $this->assertSame('orders', $table->getName());
    }

    public function testTableAcceptsOptions(): void
    {
        $table = $this->migrator->table('products', [
            'id'     => false,
            'engine' => 'InnoDB',
        ]);

        $options = $table->getOptions();

        $this->assertFalse($options['id'] ?? true);
        $this->assertSame('InnoDB', $options['engine'] ?? null);
    }

    public function testMigratorExtendsAbstractMigration(): void
    {
        $this->assertInstanceOf(\Phinx\Migration\AbstractMigration::class, $this->migrator);
    }

    public function testAdapterIsAccessible(): void
    {
        $adapter = $this->migrator->getAdapter();

        $this->assertInstanceOf(AdapterInterface::class, $adapter);
    }
}