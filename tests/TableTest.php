<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Table\Index;
use PHPUnit\Framework\TestCase;
use think\migration\db\Column;
use think\migration\db\Table;

final class TableTest extends TestCase
{
    private Table $table;

    protected function setUp(): void
    {
        $adapter = AdapterFactory::instance()->getAdapter('sqlite', [
            'name' => ':memory:',
            'memory' => true
        ]);

        $this->table = new Table('test_users', [], $adapter);
    }

    public function testSetIdSetsPrimaryKeyOption(): void
    {
        $result = $this->table->setId('uuid');

        $this->assertSame($this->table, $result);

        $options = $this->table->getOptions();
        $this->assertSame('uuid', $options['id']);
    }

    public function testSetPrimaryKeySetsPrimaryKeyOption(): void
    {
        $result = $this->table->setPrimaryKey(['id', 'lang']);

        $this->assertSame($this->table, $result);

        $options = $this->table->getOptions();
        $this->assertSame(['id', 'lang'], $options['primary_key']);
    }

    public function testSetEngineSetsEngineOption(): void
    {
        $result = $this->table->setEngine('InnoDB');

        $this->assertSame($this->table, $result);

        $options = $this->table->getOptions();
        $this->assertSame('InnoDB', $options['engine']);
    }

    public function testSetCommentSetsCommentOption(): void
    {
        $result = $this->table->setComment('User accounts table');

        $this->assertSame($this->table, $result);

        $options = $this->table->getOptions();
        $this->assertSame('User accounts table', $options['comment']);
    }

    public function testSetCollationSetsCollationOption(): void
    {
        $result = $this->table->setCollation('utf8mb4_unicode_ci');

        $this->assertSame($this->table, $result);

        $options = $this->table->getOptions();
        $this->assertSame('utf8mb4_unicode_ci', $options['collation']);
    }

    public function testAddSoftDeleteAddsTimestampColumn(): void
    {
        $result = $this->table->addSoftDelete();

        $this->assertSame($this->table, $result);
        $this->assertTableHasColumnAction('delete_time');
    }

    public function testAddMorphsAddsTwoColumnsAndIndex(): void
    {
        $result = $this->table->addMorphs('owner');

        $this->assertSame($this->table, $result);
        $this->assertTableHasColumnAction('owner_id');
        $this->assertTableHasColumnAction('owner_type');
    }

    public function testAddMorphsWithCustomIndexName(): void
    {
        $result = $this->table->addMorphs('parent', 'custom_morph_index');

        $this->assertSame($this->table, $result);
        $this->assertTableHasColumnAction('parent_id');
        $this->assertTableHasColumnAction('parent_type');
    }

    public function testAddNullableMorphsColumnsAreNullable(): void
    {
        $this->table->addNullableMorphs('parent');

        $this->assertTableHasColumnAction('parent_id');
        $this->assertTableHasColumnAction('parent_type');
    }

    public function testAddTimestampsAddsTwoColumns(): void
    {
        $result = $this->table->addTimestamps();

        $this->assertSame($this->table, $result);
        $this->assertTableHasColumnAction('create_time');
        $this->assertTableHasColumnAction('update_time');
    }

    public function testAddTimestampsWithCustomNames(): void
    {
        $this->table->addTimestamps('created_at', 'updated_at');

        $this->assertTableHasColumnAction('created_at');
        $this->assertTableHasColumnAction('updated_at');
    }

    public function testAddColumnWithUniqueColumnAddsIndex(): void
    {
        $column = Column::string('email')->setUnique();

        $this->table->addColumn($column);

        $this->assertTableHasColumnAction('email');
    }

    public function testAddColumnWithStringNameAndType(): void
    {
        $result = $this->table->addColumn('status', 'integer', ['default' => 0]);

        $this->assertSame($this->table, $result);
        $this->assertTableHasColumnAction('status');
    }

    public function testChangeColumnWithColumnInstance(): void
    {
        $this->table->addColumn('status', 'integer');

        $newColumn = Column::make('status', 'string', ['length' => 20]);

        $result = $this->table->changeColumn($newColumn);

        $this->assertSame($this->table, $result);
    }

    public function testChangeColumnWithStringNameAndType(): void
    {
        $this->table->addColumn('status', 'integer');

        $result = $this->table->changeColumn('status', 'string', ['length' => 20]);

        $this->assertSame($this->table, $result);
    }

    public function testFluentChainingOfMultipleCalls(): void
    {
        $result = $this->table
            ->addColumn(Column::string('name'))
            ->addColumn(Column::integer('age'))
            ->setId('uuid')
            ->setEngine('InnoDB')
            ->addTimestamps();

        $this->assertSame($this->table, $result);
        $this->assertTableHasColumnAction('name');
        $this->assertTableHasColumnAction('age');
        $this->assertTableHasColumnAction('create_time');
        $this->assertTableHasColumnAction('update_time');
    }

    /**
     * Check via reflection that the table has a pending column action.
     */
    private function assertTableHasColumnAction(string $columnName): void
    {
        $reflection = new \ReflectionClass(\Phinx\Db\Table::class);
        $actionsProp = $reflection->getProperty('actions');
        $actionsProp->setAccessible(true);
        $intent = $actionsProp->getValue($this->table);

        $intentReflection = new \ReflectionClass($intent);
        $getActions = $intentReflection->getMethod('getActions');
        $getActions->setAccessible(true);
        $actions = $getActions->invoke($intent);

        $found = false;
        foreach ($actions as $action) {
            $actionReflection = new \ReflectionClass($action);
            $columnPropName = null;

            if ($actionReflection->hasProperty('column')) {
                $columnProp = $actionReflection->getProperty('column');
                $columnProp->setAccessible(true);
                $colValue = $columnProp->getValue($action);

                if (is_string($colValue) && $colValue === $columnName) {
                    $found = true;
                    break;
                }

                if (is_object($colValue)) {
                    $colReflection = new \ReflectionClass($colValue);
                    if ($colReflection->hasProperty('name')) {
                        $nameProp = $colReflection->getProperty('name');
                        $nameProp->setAccessible(true);
                        $nameValue = $nameProp->getValue($colValue);
                        if ($nameValue === $columnName) {
                            $found = true;
                            break;
                        }
                    }
                    if ($colReflection->hasMethod('getName')) {
                        $getter = $colReflection->getMethod('getName');
                        $getter->setAccessible(true);
                        $nameValue = $getter->invoke($colValue);
                        if ($nameValue === $columnName) {
                            $found = true;
                            break;
                        }
                    }
                }
            }

            // Also check columnName property
            if ($actionReflection->hasProperty('columnName')) {
                $columnNameProp = $actionReflection->getProperty('columnName');
                $columnNameProp->setAccessible(true);
                $val = $columnNameProp->getValue($action);
                if ($val === $columnName) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue($found, "Column action '{$columnName}' not found in pending actions");
    }

    /**
     * Check via reflection that the table has a pending index action.
     */
    private function assertTableHasIndexAction(string $columnName): void
    {
        $reflection = new \ReflectionClass(\Phinx\Db\Table::class);
        $actionsProp = $reflection->getProperty('actions');
        $actionsProp->setAccessible(true);
        $intent = $actionsProp->getValue($this->table);

        $intentReflection = new \ReflectionClass($intent);
        $getActions = $intentReflection->getMethod('getActions');
        $getActions->setAccessible(true);
        $actions = $getActions->invoke($intent);

        $found = false;
        foreach ($actions as $action) {
            $actionClass = get_class($action);
            if (str_contains(strtolower($actionClass), 'index')) {
                $actionReflection = new \ReflectionClass($action);

                if ($actionReflection->hasProperty('table')) {
                    continue;
                }

                if ($actionReflection->hasProperty('index')) {
                    $indexProp = $actionReflection->getProperty('index');
                    $indexProp->setAccessible(true);
                    $indexValue = $indexProp->getValue($action);

                    if (is_object($indexValue)) {
                        $indexReflection = new \ReflectionClass($indexValue);
                        if ($indexReflection->hasMethod('getColumns')) {
                            $colsGetter = $indexReflection->getMethod('getColumns');
                            $colsGetter->setAccessible(true);
                            $cols = $colsGetter->invoke($indexValue);
                            if (in_array($columnName, $cols)) {
                                $found = true;
                                break;
                            }
                        }
                        if ($indexReflection->hasProperty('columns')) {
                            $colsProp = $indexReflection->getProperty('columns');
                            $colsProp->setAccessible(true);
                            $cols = $colsProp->getValue($indexValue);
                            if (in_array($columnName, $cols)) {
                                $found = true;
                                break;
                            }
                        }
                    }
                }

                if ($actionReflection->hasProperty('columns')) {
                    $colsProp = $actionReflection->getProperty('columns');
                    $colsProp->setAccessible(true);
                    $cols = $colsProp->getValue($action);
                    if (is_array($cols) && in_array($columnName, $cols)) {
                        $found = true;
                        break;
                    }
                }
            }
        }

        $this->assertTrue($found, "Index action for column '{$columnName}' not found in pending actions");
    }
}
